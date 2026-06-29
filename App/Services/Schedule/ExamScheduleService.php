<?php

namespace App\Services\Schedule;

use App\Services\BaseService;
use App\Core\Database;
use App\Enums\ExamType;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\DTOs\ScheduleItemData;
use Exception;

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
     * @param ScheduleItemData[] $dtos Ekran üzerinden gelen item DTO verileri
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

            foreach ($dtos as $dto) {
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
                if ($lesson->exam_parent_lesson_id && $lesson->examParentLesson) {
                    $mainLesson = $lesson->examParentLesson;
                }

                // Gruplu dersleri bul (aynı kod, aynı program, aynı dönem)
                $allGroupLessons = [$mainLesson];
                if ($mainLesson->group_no > 0) {
                    $siblings = (new Lesson())->get()->where([
                        'code' => $mainLesson->code,
                        'program_id' => $mainLesson->program_id,
                        'semester' => $mainLesson->semester,
                        'academic_year' => $mainLesson->academic_year,
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
                foreach ($assignments as $assignment) {
                    $assignmentOwners = [
                        ['type' => 'user', 'id' => $assignment['observer_id'], 'classroom_id' => $assignment['classroom_id']],
                        ['type' => 'classroom', 'id' => $assignment['classroom_id'], 'observer_id' => $assignment['observer_id']],
                    ];

                    foreach ($assignmentOwners as $ao) {
                        $scheduleFilters = [
                            'owner_type' => $ao['type'],
                            'owner_id' => $ao['id'],
                            'semester' => $semester,
                            'academic_year' => $academicYear,
                            'type' => $targetSchedule->type,
                            'semester_no' => null,
                        ];
                        $relSchedule = $this->scheduleRepo->findOrCreate($scheduleFilters);

                        $fullData = [
                            [
                                'lesson_id' => $lessonId,
                                'lecturer_id' => ($ao['type'] === 'user') ? $ao['id'] : $ao['observer_id'],
                                'classroom_id' => ($ao['type'] === 'classroom') ? $ao['id'] : $ao['classroom_id'],
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

                        $itemGroupedIds[$ao['type']][] = $newItem->id;
                    }
                }

                $createdIds[] = $itemGroupedIds;
                $affectedLessonIds[] = $mainLesson->id;
            }

            $this->logSaveSuccess(array_map(fn($d) => $d->toArray(), $dtos));
            return $createdIds;
        });
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
            $programItemId = $baseItem->id;
        }

        // 1. Program/Ders item'ları: aynı gün+hafta+zaman ile bul
        $programSiblings = (new ScheduleItem())
            ->get()
            ->where([
                'day_index' => $baseItem->day_index,
                'week_index' => $baseItem->week_index,
                'start_time' => $baseItem->start_time,
                'end_time' => $baseItem->end_time,
            ])
            ->all();

        // Sadece midterm/final/makeup tipi schedule'lara ait olanları filtrele
        $siblings = [];
        $siblingIds = [];

        foreach ($programSiblings as $item) {
            $itemSchedule = (new Schedule())->find($item->schedule_id);
            if ($itemSchedule && ExamType::isExamType($itemSchedule->type)) {
                $siblings[] = $item;
                $siblingIds[] = $item->id;
            }
        }

        // 2. Atama (gözetmen/derslik) item'larını detail.program_item_id üzerinden bul
        // Bu tür item'lar gün/saat eşleşmesine ek olarak detail.program_item_id'ye göre bağlıdır
        $allExamItems = (new ScheduleItem())
            ->get()
            ->where([
                'day_index' => $baseItem->day_index,
                'week_index' => $baseItem->week_index,
                'start_time' => $baseItem->start_time,
                'end_time' => $baseItem->end_time,
            ])
            ->all();

        foreach ($allExamItems as $item) {
            if (in_array($item->id, $siblingIds)) {
                continue;
            }

            $detail = $item->detail;
            if (is_string($detail)) {
                $detail = json_decode($detail, true);
            }

            if (isset($detail['program_item_id']) && $detail['program_item_id'] == $programItemId) {
                $itemSchedule = (new Schedule())->find($item->schedule_id);
                if ($itemSchedule && ExamType::isExamType($itemSchedule->type)) {
                    $siblings[] = $item;
                    $siblingIds[] = $item->id;
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
            $owners[] = ['type' => 'classroom', 'id' => $assignment['classroom_id']];
            $owners[] = ['type' => 'user', 'id' => $assignment['observer_id']];
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

        $this->logger->info(
            "$typeLabel programı düzenlendi: Eklendi/Güncellendi. Program: $screenName, Ders: $lessonName",
            $this->logContext()
        );
    }
}
