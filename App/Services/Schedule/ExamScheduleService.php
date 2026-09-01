<?php

namespace App\Services\Schedule;

use App\Core\Database;
use App\DTOs\DeleteScheduleResult;
use App\Enums\ExamType;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\DTOs\ScheduleItemDTO;
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
        $this->logger->debug("ExamService::moveExamScheduleItems START");

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
