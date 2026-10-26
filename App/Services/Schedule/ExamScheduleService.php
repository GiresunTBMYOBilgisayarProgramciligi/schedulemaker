<?php

namespace App\Services\Schedule;

use App\Core\Database;
use App\Core\Gate;
use App\DTOs\DeleteScheduleResult;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Enums\PermissionType;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\LessonCombination;
use App\Repositories\LessonAssignmentRepository;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\DTOs\ScheduleItemDTO;
use Exception;
use function App\Helpers\getClassFromSemesterNo;

/**
 * Sınav programına özgü işlemleri yönetir.
 *
 * Yalnızca midterm-exam, final-exam veya makeup-exam tipi
 * schedule'larla çalışan metodları barındırır.
 */
class ExamScheduleService extends ScheduleService
{
    // EXAM_TYPES sabiti yerine ExamType enum kullanılacak

    // ─────────────────────────────────────────────────────────────────────────
    // Hoca Sınav Görünümü
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filtreye uyan programı itemlarıyla yükler (görüntüleme ve dışa aktarma için ortak giriş noktası).
     *
     * Hoca (user) sınav programı istenirse hocanın dersleri ve gözetmenlik görevleri birleştirilir
     * (bkz. getLecturerExamSchedule); diğer tüm programlar olduğu gibi döner.
     * Hocanın henüz user programı kaydı yoksa kaydedilmemiş geçici bir program üzerinden birleştirilir.
     *
     * @param array $filter owner_type, owner_id, semester, academic_year, type ... alanlı Schedule filtresi
     * @throws Exception
     */
    public function findScheduleWithItems(array $filter): ?Schedule
    {
        $schedule = (new Schedule())->get()->where($filter)->with('items')->first();

        $isLecturerExam = ($filter['owner_type'] ?? null) === OwnerType::USER->value
            && ExamType::isExamType((string)($filter['type'] ?? ''));
        if (!$isLecturerExam) {
            return $schedule;
        }

        if (!$schedule) {
            $schedule = new Schedule();
            $schedule->owner_type = OwnerType::USER->value;
            $schedule->owner_id = (int)$filter['owner_id'];
            $schedule->semester = $filter['semester'] ?? null;
            $schedule->academic_year = $filter['academic_year'] ?? null;
            $schedule->type = $filter['type'];
            $schedule->semester_no = 0;
        }
        return $this->getLecturerExamSchedule($schedule);
    }

    /**
     * Hocanın profilinde gösterilecek birleşik sınav programını hazırlar (kaydedilmez).
     *
     * İçerik:
     * - Hocanın o dönem verdiği derslerin sınav kayıtları (gözetmen/salon bilgisiyle, hoca gözetmen olmasa da)
     * - Hocanın gözetmenlik görevleri (kendi user programı)
     *
     * Mükerrer önleme:
     * - Sınav birleştirmesinde child dersler parent'a çevrilir (child bilgisi kartta görünür)
     * - Grup kardeşleri (aynı kod/program/yarıyıl) aynı slotta tek kayıt olur, harfleri detail['group_letters'] alanında taşınır
     * - Aynı slottaki bağlantısız dersler (ör. farklı binadaki programlar) ayrı kayıt kalır
     * - Hoca kendi dersinin (veya grup kardeşinin) gözetmeni ise aynı sınavın gözetmenlik kaydı atlanır
     *
     * @param Schedule $schedule Hocanın (owner_type=user) sınav programı
     * @return Schedule Aynı program; items alanı birleşik kayıtlarla değiştirilmiştir
     * @throws Exception
     */
    public function getLecturerExamSchedule(Schedule $schedule): Schedule
    {
        $semester = $schedule->semester;
        $academicYear = $schedule->academic_year;
        $schedule->items = $schedule->id
            ? (new ScheduleItem())->get()->where(['schedule_id' => $schedule->id])->all()
            : [];

        $assignments = (new LessonAssignmentRepository())->findByLecturer($schedule->owner_id, $semester, $academicYear);
        $taughtIds = array_values(array_unique(array_filter(array_map(fn($a) => (int)$a->lesson_id, $assignments))));
        $observerLessonIds = array_values(array_unique(array_filter(
            array_map(fn(ScheduleItem $item) => (int)($item->data[0]['lesson_id'] ?? 0), $schedule->items)
        )));

        $taughtParentMap = $this->mapToExamParents($taughtIds, $semester, $academicYear);
        $observerParentMap = $this->mapToExamParents($observerLessonIds, $semester, $academicYear);
        $lessons = $this->indexLessons(array_merge(array_values($taughtParentMap), array_values($observerParentMap)));

        // Hocanın dersleri: slot başına tek kayıt
        $ownItems = [];
        $ownIdentities = [];
        $itemLessonIds = [];
        foreach ($this->getVisibleLessonExamItems($schedule, array_values(array_unique($taughtParentMap))) as [$lessonId, $item]) {
            $slot = $this->examSlotKey($item);
            $itemLessonIds[$slot . '|' . $this->examIdentity($lessonId, $lessons)][] = [$lessonId, $item];
        }
        foreach ($itemLessonIds as $entries) {
            $slot = $this->examSlotKey($entries[0][1]);
            usort($entries, fn($x, $y) => $x[0] <=> $y[0]);
            [, $item] = $entries[0];
            $letters = [];
            foreach ($entries as [$lessonId]) {
                $ownIdentities[$this->examIdentity($lessonId, $lessons) . '|' . $slot] = true;
                $letter = isset($lessons[$lessonId]) ? $lessons[$lessonId]->getGroupLetter() : null;
                if ($letter !== null) {
                    $letters[$letter] = $letter;
                }
            }
            if (count($letters) > 1) {
                ksort($letters);
                $item->detail = ['group_letters' => array_values($letters)] + ($item->detail ?? []);
            }
            $ownItems[] = $item;
        }

        // Gözetmenlik kayıtları: hocanın kendi dersine ait olanlar zaten ders kaydında görünür
        $observerItems = array_filter($schedule->items, function (ScheduleItem $item) use ($ownIdentities, $observerParentMap, $lessons) {
            $lessonId = (int)($item->data[0]['lesson_id'] ?? 0);
            $resolvedId = $observerParentMap[$lessonId] ?? $lessonId;
            return !isset($ownIdentities[$this->examIdentity($resolvedId, $lessons) . '|' . $this->examSlotKey($item)]);
        });

        $this->attachLessonLecturerNames($observerItems, $observerLessonIds, $semester, $academicYear);

        $schedule->items = array_values(array_merge($ownItems, $observerItems));
        return $schedule;
    }

    /**
     * Gözetmenlik kayıtlarına sınavı yapılan dersin hocasını detail['lesson_lecturer'] olarak ekler (kaydedilmez).
     *
     * @param ScheduleItem[] $items
     * @param int[] $lessonIds
     * @throws Exception
     */
    private function attachLessonLecturerNames(array $items, array $lessonIds, string $semester, string $academicYear): void
    {
        if (empty($items) || empty($lessonIds)) {
            return;
        }
        $lecturerNames = [];
        $lessonsWithLecturer = (new Lesson())->get()->where(['id' => ['in' => $lessonIds]])
            ->with(['lecturer' => ['semester' => $semester, 'academic_year' => $academicYear]])->all();
        foreach ($lessonsWithLecturer as $lesson) {
            if ($lesson->lecturer) {
                $lecturerNames[(int)$lesson->id] = $lesson->lecturer->getFullName();
            }
        }
        foreach ($items as $item) {
            $name = $lecturerNames[(int)($item->data[0]['lesson_id'] ?? 0)] ?? null;
            if ($name !== null) {
                $item->detail = ($item->detail ?? []) + ['lesson_lecturer' => $name];
            }
        }
    }

    /**
     * Verilen dersler (parent id'ler) için görüntüleme yetkisi olan lesson sahipli sınav kayıtlarını döner.
     *
     * @param int[] $parentIds
     * @return array<int,array{0:int,1:ScheduleItem}> [[lesson_id, item], ...]
     * @throws Exception
     */
    private function getVisibleLessonExamItems(Schedule $schedule, array $parentIds): array
    {
        if (empty($parentIds)) {
            return [];
        }
        $lessonSchedules = $this->scheduleRepo->findMultipleByOwners(
            array_map(fn($id) => ['owner_type' => 'lesson', 'owner_id' => $id], $parentIds),
            $schedule->semester,
            $schedule->academic_year,
            $schedule->type
        );
        $visibleIds = [];
        foreach ($lessonSchedules as $lessonSchedule) {
            if (Gate::check(PermissionType::VIEW->value, $lessonSchedule)) {
                $visibleIds[$lessonSchedule->id] = (int)$lessonSchedule->owner_id;
            }
        }
        if (empty($visibleIds)) {
            return [];
        }
        $items = (new ScheduleItem())->get()->where(['schedule_id' => ['in' => array_keys($visibleIds)]])->all();
        return array_map(fn(ScheduleItem $item) => [$visibleIds[$item->schedule_id], $item], $items);
    }

    /**
     * @param int[] $lessonIds
     * @return array<int,Lesson> id => Lesson
     * @throws Exception
     */
    private function indexLessons(array $lessonIds): array
    {
        $lessonIds = array_values(array_unique($lessonIds));
        if (empty($lessonIds)) {
            return [];
        }
        $indexed = [];
        foreach ((new Lesson())->get()->where(['id' => ['in' => $lessonIds]])->all() as $lesson) {
            $indexed[(int)$lesson->id] = $lesson;
        }
        return $indexed;
    }

    /**
     * Sınav olarak aynı sayılan dersin kimliği: grup kardeşleri ortak, diğerleri ders id'siyle.
     *
     * @param array<int,Lesson> $lessons
     */
    private function examIdentity(int $lessonId, array $lessons): string
    {
        $lesson = $lessons[$lessonId] ?? null;
        return ($lesson && $lesson->group_no > 0)
            ? "g:{$lesson->code}:{$lesson->program_id}:{$lesson->semester_no}"
            : "l:{$lessonId}";
    }

    /**
     * Ders id'lerini sınav birleştirmesindeki parent id'lerine çevirir (birleştirilmemiş ders kendisine eşlenir).
     *
     * @param int[] $lessonIds
     * @return array<int,int> [lesson_id => exam parent lesson_id]
     * @throws Exception
     */
    private function mapToExamParents(array $lessonIds, string $semester, string $academicYear): array
    {
        $map = array_combine($lessonIds, $lessonIds) ?: [];
        if (empty($lessonIds)) {
            return $map;
        }
        $combinations = (new LessonCombination())->get()->where([
            'child_lesson_id' => ['in' => $lessonIds],
            'type' => 'exam',
            'semester' => $semester,
            'academic_year' => $academicYear,
        ])->all();
        foreach ($combinations as $combination) {
            $map[(int)$combination->child_lesson_id] = (int)$combination->parent_lesson_id;
        }
        return $map;
    }

    private function examSlotKey(ScheduleItem $item): string
    {
        return "{$item->day_index}|{$item->week_index}|{$item->start_time}";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Günlük Sınav Sınırı (Esnek Uyarı)
    // ─────────────────────────────────────────────────────────────────────────

    /** Yönetmeliğe göre bir programda (sınıfta) bir günde yapılabilecek en fazla sınav sayısı */
    public const MAX_EXAMS_PER_DAY = 2;

    /**
     * Eklenecek/taşınacak sınavlar sonrası bir programda aynı gün MAX_EXAMS_PER_DAY'den fazla
     * sınav oluşuyorsa uyarı mesajlarını döner. Engelleyici değildir; kullanıcı onaylarsa kayıt yapılır.
     *
     * Aynı saat diliminde olan sınavlar (gruplar, sınav birleştirmeleri) tek sınav sayılır.
     * Taşıma/düzenleme sırasında silinecek sınavlar sayıma dahil edilmez.
     *
     * @param ScheduleItemDTO[] $dtos Eklenecek item DTO'ları
     * @param ScheduleItemDTO[] $deletedDtos Aynı işlemde silinecek item DTO'ları
     * @return string[] Uyarı mesajları (boşsa sorun yok)
     * @throws Exception
     */
    public function checkDailyExamLimit(array $dtos, array $deletedDtos = []): array
    {
        $excludedIds = [];
        foreach ($deletedDtos as $deletedDto) {
            $id = (int) ($deletedDto->id ?? 0);
            if (!$id) {
                continue;
            }
            $item = (new ScheduleItem())->find($id);
            if (!$item) {
                continue;
            }
            foreach ($this->findExamSiblingItems($item) as $sibling) {
                $excludedIds[(int) $sibling->id] = true;
            }
        }

        // "programKey|gün|hafta" => ['schedule' => Schedule, 'slots' => [slotKey => etiket]]
        $targets = [];

        foreach ($dtos as $dto) {
            $lessonId = $dto->data[0]['lesson_id'] ?? $dto->data['lesson_id'] ?? null;
            $lesson = $lessonId ? (new Lesson())->where(['id' => $lessonId])->with(['examChildLessons', 'examParentLesson'])->first() : null;
            $targetSchedule = (new Schedule())->find($dto->scheduleId);
            if (!$lesson || !$targetSchedule) {
                continue;
            }

            $newSlot = "{$dto->startTime}|{$dto->endTime}";
            $newLabel = $lesson->getFullName(true, false, true, true) ?: $lesson->code;

            foreach ($this->resolveExamPrograms($lesson) as $program) {
                $key = "{$program['program_id']}|{$program['semester_no']}|{$dto->dayIndex}|{$dto->weekIndex}";
                if (!isset($targets[$key])) {
                    $relSchedule = (new Schedule())->get()->where([
                        'owner_type' => OwnerType::PROGRAM->value,
                        'owner_id' => $program['program_id'],
                        'semester_no' => $program['semester_no'],
                        'semester' => $targetSchedule->semester,
                        'academic_year' => $targetSchedule->academic_year,
                        'type' => $targetSchedule->type,
                    ])->first();

                    $slots = [];
                    if ($relSchedule) {
                        $dayItems = (new ScheduleItem())->get()->where([
                            'schedule_id' => $relSchedule->id,
                            'day_index' => $dto->dayIndex,
                            'week_index' => $dto->weekIndex,
                        ])->all();
                        foreach ($dayItems as $existing) {
                            if (isset($excludedIds[(int) $existing->id]) || !in_array($existing->status, ['single', 'group'])) {
                                continue;
                            }
                            $existingLessonId = (int) ($existing->data[0]['lesson_id'] ?? 0);
                            $existingLesson = $existingLessonId ? (new Lesson())->find($existingLessonId) : null;
                            $slots["{$existing->start_time}|{$existing->end_time}"] = $existingLesson?->getFullName(true, false, true, true) ?? $existingLesson?->code ?? '';
                        }
                    }

                    $targets[$key] = [
                        'program_id' => $program['program_id'],
                        'semester_no' => $program['semester_no'],
                        'day_index' => $dto->dayIndex,
                        'slots' => $slots,
                    ];
                }
                $targets[$key]['slots'][$newSlot] = $newLabel;
            }
        }

        $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
        $warnings = [];
        foreach ($targets as $target) {
            $count = count($target['slots']);
            if ($count <= self::MAX_EXAMS_PER_DAY) {
                continue;
            }
            $programName = (new Program())->find($target['program_id'])?->name ?? '';
            $classLabel = $target['semester_no'] ? getClassFromSemesterNo($target['semester_no']) . ". Sınıf" : '';
            $dayName = $days[$target['day_index']] ?? 'Bu gün';
            $names = implode(', ', array_filter($target['slots']));
            $warnings[] = trim("{$programName} {$classLabel}") . ": {$dayName} günü {$count} sınav olacak"
                . ($names !== '' ? " ({$names})" : '')
                . ". Yönetmeliğe göre bir günde en fazla " . self::MAX_EXAMS_PER_DAY . " sınav yapılabilir.";
        }

        return $warnings;
    }

    /**
     * Bir sınavdan etkilenen benzersiz program (program_id + semester_no) listesini döner.
     * saveExamScheduleItems'taki program owner belirleme mantığıyla aynıdır
     * (exam parent, gruplu dersler ve sınav birleştirmeleri).
     *
     * @return array<int, array{program_id: int, semester_no: int}>
     */
    private function resolveExamPrograms(Lesson $lesson): array
    {
        $mainLesson = !empty($lesson->examParentLesson) ? $lesson->examParentLesson : $lesson;

        $lessons = [$mainLesson];
        if ($mainLesson->group_no > 0) {
            $siblings = (new Lesson())->get()->where([
                'code' => $mainLesson->code,
                'program_id' => $mainLesson->program_id,
                'semester_no' => $mainLesson->semester_no,
                'group_no' => ['>' => 0],
                'id' => ['!=' => $mainLesson->id]
            ])->with(['examChildLessons'])->all();
            $lessons = array_merge($lessons, $siblings);
        }

        $programs = [];
        foreach ($lessons as $l) {
            $programs["{$l->program_id}|{$l->semester_no}"] = ['program_id' => (int) $l->program_id, 'semester_no' => (int) $l->semester_no];
            foreach ($l->examChildLessons ?? [] as $child) {
                $programs["{$child->program_id}|{$child->semester_no}"] = ['program_id' => (int) $child->program_id, 'semester_no' => (int) $child->semester_no];
            }
        }

        return array_values($programs);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sınav Item Kayıt
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sınav item'larını kaydeder ve kardeş programlara (Program, Ders, Gözetmen, Derslik) yansıtır.
     *
     * Sınava özgü mantık:
     * - Program ve ders kayıtlarında yalnızca lesson_id tutulur (lecturer_id/classroom_id null)
     * - Gözetmen ve derslik kayıtlarında tam veri + primaryProgramItemId referansı eklenir
     * - Birden fazla atama (assignments) desteklenir
     *
     * @param ScheduleItemDTO[] $dtos Ekran üzerinden gelen item DTO verileri
     * @return array Oluşturulan ID'ler (owner_type bazlı gruplu)
     * @throws Exception
     */
    public function saveExamScheduleItems(array $dtos): array
    {
        $this->logger->debug(
            "ExamService::saveExamScheduleItems START. Item Count: " . count($dtos),
            $this->logContext()
        );

        return Database::transaction(function () use ($dtos) {
            $createdIds = [];
            $affectedLessonIds = [];
            $affectedScheduleIds = [];

            foreach ($dtos as $dto) {
                $affectedScheduleIds[] = (int) $dto->scheduleId;
                $dayIndex = $dto->dayIndex;
                $startTime = $dto->startTime;
                $endTime = $dto->endTime;
                $weekIndex = $dto->weekIndex;

                $lessonId = $dto->data[0]['lesson_id'] ?? $dto->data['lesson_id'] ?? null;
                $lesson = (new Lesson())
                    ->where(['id' => $lessonId])
                    ->with(['childLessons', 'parentLesson', 'examChildLessons', 'examParentLesson'])
                    ->first();

                if (!$lesson) {
                    throw new Exception("Ders bulunamadı");
                }

                $targetSchedule = (new Schedule())->find($dto->scheduleId);
                if (!$targetSchedule) {
                    throw new Exception("Hedef program bulunamadı");
                }

                $semester = $targetSchedule->semester;
                $academicYear = $targetSchedule->academic_year;

                // ── 1. Program ve Ders Owner'larını Belirle ──────────────────────
                // Sınav programında sadece exam_parent_lesson_id dikkate alınır
                // (parent_lesson_id ders programı içindir, sınav programını etkilemez)
                $mainLesson = $lesson;
                if (!empty($lesson->examParentLesson)) {
                    $mainLesson = $lesson->examParentLesson;
                }

                // Gruplu dersleri bul (aynı kod, aynı program, aynı dönem)
                $allGroupLessons = [$mainLesson];
                if ($mainLesson->group_no > 0) {
                    $siblings = (new Lesson())->get()->where([
                        'code' => $mainLesson->code,
                        'program_id' => $mainLesson->program_id,
                        'semester_no' => $mainLesson->semester_no,
                        'group_no' => ['>' => 0],
                        'id' => ['!=' => $mainLesson->id]
                    ])->all();
                    $allGroupLessons = array_merge($allGroupLessons, $siblings);
                }


                $programOwners = [];
                foreach ($allGroupLessons as $gl) {
                    $programOwners[] = ['type' => 'lesson', 'id' => $gl->id, 'actual_lesson_id' => $gl->id];
                    $programOwners[] = ['type' => 'program', 'id' => $gl->program_id, 'semester_no' => $gl->semester_no, 'actual_lesson_id' => $gl->id];

                    // Sınav birleştirme (exam_parent_lesson_id) ile bağlı dersler
                    foreach ($gl->examChildLessons ?? [] as $examChild) {
                        $programOwners[] = ['type' => 'lesson', 'id' => $examChild->id, 'actual_lesson_id' => $examChild->id];
                        $programOwners[] = ['type' => 'program', 'id' => $examChild->program_id, 'semester_no' => $examChild->semester_no, 'actual_lesson_id' => $examChild->id];
                    }
                }

                // Unique owner'lar (aynı program birden fazla çocuk derse sahip olabilir)
                $uniqueProgramOwners = [];
                foreach ($programOwners as $po) {
                    $key = $po['type'] . '_' . $po['id'] . '_' . ($po['semester_no'] ?? '');
                    $uniqueProgramOwners[$key] = $po;
                }

                // ── 2. Çakışma Kontrolü ───────────────────────────────────────────
                $conflictService = new ConflictService();
                $errors = [];
                $conflictService->checkScheduleCrash(['items' => json_encode([$dto->toArray()])]);

                // ── 3. Program ve Ders Kayıtları (süzülmüş veri) ─────────────────
                $itemGroupedIds = [];
                $primaryProgramItemId = null;

                foreach ($uniqueProgramOwners as $owner) {
                    $scheduleFilters = [
                        'owner_type' => $owner['type'],
                        'owner_id' => $owner['id'],
                        'semester' => $semester,
                        'academic_year' => $academicYear,
                        'type' => $targetSchedule->type,
                        'semester_no' => ($owner['type'] === 'program') ? $owner['semester_no'] : null,
                    ];
                    $relSchedule = $this->scheduleRepo->findOrCreate($scheduleFilters);
                    $affectedScheduleIds[] = (int) $relSchedule->id;

                    // Sınav program/ders kaydında yalnızca lesson_id
                    $filteredData = [
                        [
                            'lesson_id' => $owner['actual_lesson_id'] ?? (($owner['type'] === 'lesson') ? $owner['id'] : $mainLesson->id),
                            'lecturer_id' => null,
                            'classroom_id' => null,
                        ]
                    ];

                    $newItem = new ScheduleItem();
                    $newItem->schedule_id = $relSchedule->id;
                    $newItem->day_index = $dayIndex;
                    $newItem->week_index = $weekIndex;
                    $newItem->start_time = $startTime;
                    $newItem->end_time = $endTime;
                    $newItem->status = 'single';
                    $newItem->data = $filteredData;
                    $newItem->detail = $dto->detail;
                    $newItem->create();

                    $itemGroupedIds[$owner['type']][] = $newItem->id;

                    if ($relSchedule->id === $targetSchedule->id) {
                        $primaryProgramItemId = $newItem->id;
                    }
                }

                // ── 4. Gözetmen ve Derslik Kayıtları (tam veri + referans) ───────
                $assignments = $dto->detail['assignments'] ?? [];
                $processedClassroomIds = [];

                foreach ($assignments as $assignment) {
                    $classroomId = (int)($assignment['classroom_id'] ?? 0);

                    // Gözetmenleri topla
                    $observers = [];
                    if (!empty($assignment['observers']) && is_array($assignment['observers'])) {
                        foreach ($assignment['observers'] as $obs) {
                            $obsId = is_array($obs) ? ($obs['id'] ?? null) : $obs;
                            $obsName = is_array($obs) ? ($obs['name'] ?? '') : '';
                            if ($obsId) {
                                $observers[] = ['id' => (int)$obsId, 'name' => $obsName];
                            }
                        }
                    } elseif (!empty($assignment['observer_id'])) {
                        $observers[] = [
                            'id' => (int)$assignment['observer_id'],
                            'name' => $assignment['observer_name'] ?? ''
                        ];
                    }

                    $firstObserverId = !empty($observers) ? $observers[0]['id'] : null;

                    // A) Derslik Kaydı (Her derslik için tek bir ScheduleItem)
                    if ($classroomId && !in_array($classroomId, $processedClassroomIds)) {
                        $processedClassroomIds[] = $classroomId;

                        $scheduleFilters = [
                            'owner_type' => 'classroom',
                            'owner_id' => $classroomId,
                            'semester' => $semester,
                            'academic_year' => $academicYear,
                            'type' => $targetSchedule->type,
                            'semester_no' => null,
                        ];
                        $relSchedule = $this->scheduleRepo->findOrCreate($scheduleFilters);
                        $affectedScheduleIds[] = (int) $relSchedule->id;

                        $fullData = [
                            [
                                'lesson_id' => $lessonId,
                                'lecturer_id' => $firstObserverId,
                                'classroom_id' => $classroomId,
                            ]
                        ];

                        $newItem = new ScheduleItem();
                        $newItem->schedule_id = $relSchedule->id;
                        $newItem->day_index = $dayIndex;
                        $newItem->week_index = $weekIndex;
                        $newItem->start_time = $startTime;
                        $newItem->end_time = $endTime;
                        $newItem->status = 'single';
                        $newItem->data = $fullData;
                        $newItem->detail = [
                            'program_item_id' => $primaryProgramItemId,
                            'reference_type' => 'exam_assignment',
                            'observers' => $observers
                        ];
                        $newItem->create();

                        $itemGroupedIds['classroom'][] = $newItem->id;
                    }

                    // B) Her bir gözetmen için User Kaydı
                    foreach ($observers as $obs) {
                        $observerId = $obs['id'];
                        $scheduleFilters = [
                            'owner_type' => 'user',
                            'owner_id' => $observerId,
                            'semester' => $semester,
                            'academic_year' => $academicYear,
                            'type' => $targetSchedule->type,
                            'semester_no' => null,
                        ];
                        $relSchedule = $this->scheduleRepo->findOrCreate($scheduleFilters);
                        $affectedScheduleIds[] = (int) $relSchedule->id;

                        $fullData = [
                            [
                                'lesson_id' => $lessonId,
                                'lecturer_id' => $observerId,
                                'classroom_id' => $classroomId,
                            ]
                        ];

                        $newItem = new ScheduleItem();
                        $newItem->schedule_id = $relSchedule->id;
                        $newItem->day_index = $dayIndex;
                        $newItem->week_index = $weekIndex;
                        $newItem->start_time = $startTime;
                        $newItem->end_time = $endTime;
                        $newItem->status = 'single';
                        $newItem->data = $fullData;
                        $newItem->detail = [
                            'program_item_id' => $primaryProgramItemId,
                            'reference_type' => 'exam_assignment',
                        ];
                        $newItem->create();

                        $itemGroupedIds['user'][] = $newItem->id;
                    }
                }

                $createdIds[] = $itemGroupedIds;
                $affectedLessonIds[] = $mainLesson->id;
            }

            // Etkilenen tüm schedule'ların updated_at zamanını güncelle
            if (!empty($affectedScheduleIds)) {
                $this->touchSchedules($affectedScheduleIds);
            }

            $this->logSaveSuccess(array_map(fn($d) => $d->toArray(), $dtos));
            return $createdIds;
        });
    }

    /**
     * Sınav programında taşıma işlemi (silme ve ekleme tek transaction'da)
     */
    public function moveExamScheduleItems(array $dtos, array $deletedDtos): array
    {
        $this->logger->debug("ExamService::moveExamScheduleItems START", $this->logContext());

        return Database::transaction(function () use ($dtos, $deletedDtos) {
            if (!empty($deletedDtos)) {
                $this->deleteScheduleItems($deletedDtos);
            }

            return $this->saveExamScheduleItems($dtos);
        });
    }

    /**
     * Sınav programı öğelerini ve ilişkili tüm kardeş kayıtları (gözetmenler, derslikler, program, ders) siler.
     * Sınavlarda parçalı dilim silme veya yeniden segment oluşturma yoktur; sınav bütünüyle kaldırılır.
     *
     * @param ScheduleItemDTO[] $dtos Silinecek sınav item DTO'ları
     * @param bool $expandGroup Sınavlar için geçerli değildir
     * @return DeleteScheduleResult
     */
    public function deleteScheduleItems(array $dtos, bool $expandGroup = true): DeleteScheduleResult
    {
        $this->logger->debug("ExamScheduleService::deleteScheduleItems START", $this->logContext(['count' => count($dtos)]));

        try {
            return Database::transaction(function () use ($dtos) {
                $processedSiblingIds = [];
                $deletedIds = [];
                $affectedScheduleIds = [];

                foreach ($dtos as $dto) {
                    $id = (int) ($dto->id ?? 0);
                    if (!$id || in_array($id, $processedSiblingIds)) {
                        continue;
                    }

                    $scheduleItem = (new ScheduleItem())
                        ->where(['id' => $id])
                        ->with('schedule')
                        ->first();

                    if (!$scheduleItem) {
                        continue;
                    }

                    if ($scheduleItem->schedule_id) {
                        $affectedScheduleIds[] = (int) $scheduleItem->schedule_id;
                    }

                    if (!empty($scheduleItem->detail['is_locked'])) {
                        throw new Exception("Kilitli olan öğeler üzerinde değişiklik yapılamaz.");
                    }

                    // Sınava bağlı tüm kardeş kayıtları (Program, Ders, Derslikler, Gözetmenler) bul
                    $siblings = $this->findExamSiblingItems($scheduleItem);

                    foreach ($siblings as $sibling) {
                        $siblingId = (int) $sibling->id;
                        if (!in_array($siblingId, $deletedIds)) {
                            if ($sibling->schedule_id) {
                                $affectedScheduleIds[] = (int) $sibling->schedule_id;
                            }
                            $sibling->delete();
                            $deletedIds[] = $siblingId;
                            $processedSiblingIds[] = $siblingId;
                        }
                    }
                }

                // Etkilenen tüm schedule'ların updated_at zamanını güncelle
                if (!empty($affectedScheduleIds)) {
                    $this->touchSchedules($affectedScheduleIds);
                }

                $this->logger->debug(
                    "ExamScheduleService::deleteScheduleItems SUCCESS. Silinen item sayısı: " . count($deletedIds),
                    $this->logContext(['deletedIds' => $deletedIds, 'affected_schedule_count' => count(array_unique($affectedScheduleIds))])
                );

                return DeleteScheduleResult::success($deletedIds, []);
            });
        } catch (Exception $e) {
            $this->logger->error(
                "ExamScheduleService::deleteScheduleItems ERROR: " . $e->getMessage(),
                $this->logContext(['exception' => $e])
            );
            return DeleteScheduleResult::failure($e->getMessage());
        }
    }
    // ─────────────────────────────────────────────────────────────────────────
    // Sınav Sibling
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sınav item'ı için kardeş schedule'lardaki kopyaları bulur.
     *
     * Sınav item'larının referans zinciri:
     * - Program/Ders item'ları: doğrudan gün+hafta+zaman eşleşmesi
     * - Gözetmen/Derslik item'ları: detail.program_item_id = primaryProgramItemId
     *
     * @param ScheduleItem $baseItem Kaynak item
     * @return ScheduleItem[] Kardeş item'lar (baseItem dahil)
     * @throws Exception
     */
    public function findExamSiblingItems(ScheduleItem $baseItem): array
    {
        $schedule = $baseItem->schedule
            ?? (new Schedule())->find($baseItem->schedule_id);

        if (!$schedule || !ExamType::isExamType($schedule->type)) {
            return [$baseItem];
        }

        $baseDetail = $baseItem->detail;
        if (is_string($baseDetail)) {
            $baseDetail = json_decode($baseDetail, true);
        }

        // Bu item bir "atama" item'ı ise (gözetmen/derslik), onun program_item_id'sini kullan
        $programItemId = null;
        if (isset($baseDetail['reference_type']) && $baseDetail['reference_type'] === 'exam_assignment') {
            $programItemId = $baseDetail['program_item_id'] ?? null;
        }

        // Eğer bu bir program/ders item'ı ise, kendisi primaryProgramItemId
        if (!$programItemId) {
            $programItemId = $baseDetail['program_item_id'] ?? $baseItem->id;
        }

        $primaryItem = ($programItemId == $baseItem->id) ? $baseItem : (new ScheduleItem())->find($programItemId);

        // İlgili ders ID'lerini bul
        $lessonId = $primaryItem?->data[0]['lesson_id'] ?? $baseItem->data[0]['lesson_id'] ?? null;
        $relatedLessonIds = [];
        if ($lessonId) {
            $lesson = (new Lesson())->where(['id' => $lessonId])->with(['examChildLessons', 'examParentLesson'])->first();
            if ($lesson) {
                $mainLesson = !empty($lesson->examParentLesson) ? $lesson->examParentLesson : $lesson;
                $relatedLessonIds[] = (int)$mainLesson->id;
                foreach ($mainLesson->examChildLessons ?? [] as $child) {
                    $relatedLessonIds[] = (int)$child->id;
                }
                if ($mainLesson->group_no > 0) {
                    $groupSiblings = (new Lesson())->get()->where([
                        'code' => $mainLesson->code,
                        'program_id' => $mainLesson->program_id,
                        'semester_no' => $mainLesson->semester_no,
                        'group_no' => ['>' => 0]
                    ])->all();
                    foreach ($groupSiblings as $sib) {
                        $relatedLessonIds[] = (int)$sib->id;
                    }
                }
            }
        }
        $relatedLessonIds = array_unique($relatedLessonIds);

        // 1. Aynı zaman dilimindeki sınav item'larını getir ve filtrele
        $candidates = (new ScheduleItem())
            ->get()
            ->where([
                'day_index' => $baseItem->day_index,
                'week_index' => $baseItem->week_index,
                'start_time' => $baseItem->start_time,
                'end_time' => $baseItem->end_time,
            ])
            ->all();

        $siblings = [];
        $siblingIds = [];
        $scheduleCache = [$schedule->id => $schedule];

        foreach ($candidates as $item) {
            $detail = $item->detail;
            if (is_string($detail)) {
                $detail = json_decode($detail, true);
            }

            $isMatch = false;

            // a) Bu item primary program item'ın kendisi mi?
            if ($item->id == $programItemId) {
                $isMatch = true;
            }
            // b) Atama item'ı mı ve program_item_id eşleşiyor mu?
            elseif (isset($detail['program_item_id']) && $detail['program_item_id'] == $programItemId) {
                $isMatch = true;
            }
            // c) Program/ders item'ı ve ders ID'si bu sınava ait derslerden biri mi?
            else {
                $itemLessonId = (int)($item->data[0]['lesson_id'] ?? 0);
                if ($itemLessonId && in_array($itemLessonId, $relatedLessonIds)) {
                    $isMatch = true;
                }
            }

            if ($isMatch) {
                $schedId = $item->schedule_id;
                if (!isset($scheduleCache[$schedId])) {
                    $scheduleCache[$schedId] = (new Schedule())->find($schedId);
                }
                $itemSchedule = $scheduleCache[$schedId];

                if ($itemSchedule && ExamType::isExamType($itemSchedule->type)) {
                    if (!in_array($item->id, $siblingIds)) {
                        $siblings[] = $item;
                        $siblingIds[] = $item->id;
                    }
                }
            }
        }

        return $siblings ?: [$baseItem];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sınav için owner listesini belirler.
     * (ScheduleService::saveToMultipleSchedules yerine ExamService::saveExamScheduleItems kullanır)
     *
     * @param Lesson $lesson         Sınav dersi
     * @param array  $examAssignments Gözetmen-derslik atamaları
     * @return array Owner listesi
     */
    public function determineExamOwners(Lesson $lesson, array $examAssignments): array
    {
        $owners = [
            ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no],
            ['type' => 'lesson', 'id' => $lesson->id],
        ];

        foreach ($examAssignments as $assignment) {
            if (!empty($assignment['classroom_id'])) {
                $owners[] = ['type' => 'classroom', 'id' => $assignment['classroom_id']];
            }
            if (!empty($assignment['observers']) && is_array($assignment['observers'])) {
                foreach ($assignment['observers'] as $obs) {
                    $obsId = is_array($obs) ? ($obs['id'] ?? null) : $obs;
                    if ($obsId) {
                        $owners[] = ['type' => 'user', 'id' => (int)$obsId];
                    }
                }
            } elseif (!empty($assignment['observer_id'])) {
                $owners[] = ['type' => 'user', 'id' => (int)$assignment['observer_id']];
            }
        }

        return $owners;
    }

    /**
     * Kayıt başarı logu.
     */
    private function logSaveSuccess(array $itemsData): void
    {
        $scheduleId = $itemsData[0]['schedule_id'] ?? null;
        $schedule = $scheduleId ? (new Schedule())->find($scheduleId) : null;
        $screenName = $schedule ? $schedule->getScheduleScreenName() : "";
        $typeLabel = $schedule ? $schedule->getScheduleTypeName() : "sınav";

        $lessonNames = [];
        foreach ($itemsData as $item) {
            $lId = $item['data'][0]['lesson_id'] ?? null;
            if ($lId) {
                $lessonObj = (new Lesson())->find($lId);
                if ($lessonObj) {
                    $name = $lessonObj->getFullName(addCode: true, addProgram: true,addGroup: true,addClassNumber: true);
                    if (!in_array($name, $lessonNames)) {
                        $lessonNames[] = $name;
                    }
                }
            }
        }

        $lessonName = !empty($lessonNames) ? implode(", ", $lessonNames) : "Bilinmeyen Ders";

        $this->logger->debug(
            "$typeLabel programı düzenlendi: Eklendi/Güncellendi. Program: $screenName, Ders: $lessonName",
            $this->logContext()
        );
    }
}
