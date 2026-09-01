<?php

namespace App\Services\Schedule;

use App\Core\Database;
use App\DTOs\DeleteScheduleResult;
use App\DTOs\ScheduleItemDTO;
use App\Exceptions\ValidationException;
use App\Helpers\TimeHelper;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\DTOs\SaveScheduleResult;
use App\Enums\ScheduleItemStatus;
use App\Services\Schedule\ConflictService;
use Exception;
use function App\Helpers\getSettingValue;

class LessonScheduleService extends ScheduleService
{
    /**
     * Ders programına yeni öğe(ler) ekler
     *
     * @param ScheduleItemDTO[] $dtos Ekran üzerinden gelen item DTO verileri
     * @return SaveScheduleResult
     * @throws Exception
     */
    public function saveScheduleItems(array $dtos): SaveScheduleResult
    {
        $this->logger->debug("LessonScheduleService::saveScheduleItems START", $this->logContext(['count' => count($dtos)]));

        // 1. Validation - batch olarak tüm item'ları kontrol et
        $itemsData = array_map(fn($dto) => $dto->toArray(), $dtos);
        $this->validator->validateBatch($itemsData);

        // 2. Çakışma Kontrolü (ConflictService üzerinden)
        $conflictService = new ConflictService();
        $conflictService->checkScheduleCrash(['items' => json_encode($itemsData)]);

        try {
            return Database::transaction(function () use ($dtos) {
                $createdIds = [];
                $affectedLessonIds = [];
                $affectedScheduleIds = [];
                $this->logger->debug("Starting transaction for saving schedule items", $this->logContext(['dtos_count' => count($dtos)]));
                foreach ($dtos as $index => $dto) {
                    $this->logger->debug("Processing item #$index", $this->logContext(['itemData' => $dto->toArray()]));

                    // İlgili bilgileri al
                    /** @var Schedule $schedule */
                    $schedule = $this->scheduleRepo->find($dto->scheduleId);
                    if (!$schedule) {
                        throw new Exception("Schedule not found: {$dto->scheduleId}");
                    }
                    $affectedScheduleIds[] = (int) $schedule->id;

                    $isDummy = $dto->isDummy();
                    $isGroup = ($dto->status === ScheduleItemStatus::GROUP->value);
                    $lesson = null;

                    // Dummy olmayan itemlar için lesson bilgisini al (child lessons ile birlikte)
                    if (!$isDummy) {
                        $lessonId = null;

                        // data bir array of arrays, ilk elemanı kontrol et
                        if (!empty($dto->data) && isset($dto->data[0]['lesson_id'])) {
                            $lessonId = $dto->data[0]['lesson_id'];
                        }

                        if ($lessonId) {
                            $lesson = (new Lesson())->where(['id' => $lessonId])->with(['childLessons'])->first();
                            if (!$lesson) {
                                throw new Exception("Lesson not found: {$lessonId}");
                            }
                        }
                    }

                    if ($isGroup) {
                        // GROUP ITEM: mergeGroupItems kullanarak multi-schedule'a kaydet
                        $itemIds = $this->saveGroupItemToSchedules($dto, $lesson, $schedule, $affectedScheduleIds);
                    } else {
                        // SINGLE/DUMMY ITEM
                        // MULTI-SCHEDULE KAYDETME: Tüm ilgili schedule'lara kaydet
                        $itemIds = $this->saveToMultipleSchedules($dto, $lesson, $schedule, $affectedScheduleIds);
                    }

                    $createdIds = array_merge($createdIds, $itemIds);

                    $this->logger->debug("Item #{$index} saved to " . count($itemIds) . " schedules", $this->logContext([
                        'item_ids' => $itemIds,
                        'is_group' => $isGroup
                    ]));

                    // Etkilenen ders ID'lerini kaydet (child lessons dahil)
                    if (!$isDummy && $lesson) {
                        $affectedLessonIds[] = $lesson->id;
                        if (!empty($lesson->childLessons)) {
                            foreach ($lesson->childLessons as $cl) {
                                $affectedLessonIds[] = $cl->id;
                            }
                        }
                    }
                }

                // Ders saati kontrolü
                if (!empty($affectedLessonIds)) {
                    $this->checkLessonHourLimits(array_unique($affectedLessonIds), $schedule->type);
                }

                // Etkilenen tüm schedule'ların updated_at zamanını güncelle
                if (!empty($affectedScheduleIds)) {
                    $this->touchSchedules($affectedScheduleIds);
                }

                $this->logger->debug("Schedule items saved successfully", $this->logContext([
                    'created_count' => count($createdIds),
                    'schedule_id' => $dtos[0]->scheduleId ?? null,
                    'affected_schedule_count' => count(array_unique($affectedScheduleIds))
                ]));

                return SaveScheduleResult::success($createdIds, count($dtos));
            });
        } catch (Exception $e) {
            $this->logger->error("LessonScheduleService::saveScheduleItems ERROR: " . $e->getMessage(), $this->logContext([
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]));
            
            if ($e instanceof ValidationException) {
                return new SaveScheduleResult([], 0, [$e->getMessage()], false);
            }
            return new SaveScheduleResult([], 0, ["Program kaydedilirken bir hata oluştu."], false);
        }
    }

    /**
     * Sürükle bırak ile taşıma işleminde kullanılır. Önce siler, sonra kaydeder.
     * Transaction içinde yapıldığı için hata durumunda silme işlemi de geri alınır.
     *
     * @param ScheduleItemDTO[] $dtos Eklenecek veriler
     * @param ScheduleItemDTO[] $deletedDtos Silinecek veriler
     * @return SaveScheduleResult
     * @throws Exception
     */
    public function moveScheduleItems(array $dtos, array $deletedDtos): SaveScheduleResult
    {
        $this->logger->debug("LessonScheduleService::moveScheduleItems START");

        try {
            return Database::transaction(function () use ($dtos, $deletedDtos) {
                if (!empty($deletedDtos)) {
                    $this->deleteScheduleItems($deletedDtos);
                }
                
                $saveResult = $this->saveScheduleItems($dtos);
                
                if (!$saveResult->success) {
                    throw new Exception(implode("\n", $saveResult->warnings));
                }
                
                return $saveResult;
            });
        } catch (Exception $e) {
            return new SaveScheduleResult([], 0, [$e->getMessage()], false);
        }
    }

    /**
     * Tekil item'ı ilgili tüm schedule'lara kaydeder
     */
    protected function saveToMultipleSchedules(
        ScheduleItemDTO $dto,
        ?Lesson $lesson,
        Schedule $sourceSchedule,
        array &$affectedScheduleIds = []
    ): array {
        $owners = array_map(function ($o) use ($lesson) {
            if (!isset($o['is_child']) || !$o['is_child']) {
                $o['lesson_context'] = $lesson;
            }
            return $o;
        }, $this->determineOwners($dto, $lesson));
        $createdIds = [];

        $this->logger->debug('saveToMultipleSchedules: Owner list determined', [
            'owner_count' => count($owners)
        ]);

        if ($lesson && !$dto->isDummy()) {
            $lesson->IsScheduleComplete($sourceSchedule->type);

            $lessonType = ($sourceSchedule->type === 'lesson') ? 'lesson' : 'exam';
            $slotSize = (int) getSettingValue('duration', $lessonType, $lessonType === 'exam' ? 30 : 50) +
                        (int) getSettingValue('break', $lessonType, $lessonType === 'exam' ? 0 : 10);

            $addedSlots = TimeHelper::calculateItemSlots($dto->startTime, $dto->endTime, $slotSize);

            if ($lesson->remaining_size < $addedSlots) {
                $errorMsg = ($sourceSchedule->type === 'lesson')
                    ? "{$lesson->getFullName()} dersinin toplam saati aşılıyor. (Kalan: {$lesson->remaining_size} saat, Eklenmek istenen: {$addedSlots} saat)"
                    : "{$lesson->getFullName()} dersinin sınav mevcudu aşılıyor. (Kalan: {$lesson->remaining_size}, Eklenmek istenen: {$addedSlots})";

                throw new Exception($errorMsg);
            }
        }

        $childLessonRemaining = [];
        foreach ($owners as $owner) {
            if (!isset($owner['is_child']) || !$owner['is_child']) {
                continue;
            }
            $childLessonId = $owner['child_lesson_id'];
            if (isset($childLessonRemaining[$childLessonId])) {
                continue;
            }
            $childLesson = (new Lesson())->where(['id' => $childLessonId])->with([
                'lecturer' => ['semester' => $sourceSchedule->semester, 'academic_year' => $sourceSchedule->academic_year]
            ])->first();
            if ($childLesson) {
                $childLesson->IsScheduleComplete($sourceSchedule->type, $sourceSchedule->semester, $sourceSchedule->academic_year);
                $childLessonRemaining[$childLessonId] = [
                    'lesson' => $childLesson,
                    'remaining' => (int) ($childLesson->remaining_size ?? 0),
                ];
            }
        }

        $childLessonHoursAdded = [];

        foreach ($owners as $owner) {
            /** @var Schedule $targetSchedule */
            $targetSchedule = $this->findOrCreateSchedule(
                $owner,
                $sourceSchedule->academic_year,
                $sourceSchedule->semester,
                $sourceSchedule->type
            );
            $affectedScheduleIds[] = (int) $targetSchedule->id;

            $item = new ScheduleItem();
            $item->schedule_id = $targetSchedule->id;
            $item->day_index = $dto->dayIndex;
            $item->week_index = $dto->weekIndex;
            $item->start_time = $dto->startTime;
            $item->end_time = $dto->endTime;
            $item->status = $dto->status;

            if (isset($owner['is_child']) && $owner['is_child']) {
                $childLessonId = $owner['child_lesson_id'];

                if (!isset($childLessonRemaining[$childLessonId])) {
                    continue;
                }
                $childLesson = $childLessonRemaining[$childLessonId]['lesson'];
                $baseRemaining = $childLessonRemaining[$childLessonId]['remaining'];

                $trackingKey = "{$childLessonId}_{$owner['type']}";
                $alreadyAddedSlots = $childLessonHoursAdded[$trackingKey] ?? 0;
                $currentRemaining = $baseRemaining - $alreadyAddedSlots;

                if ($currentRemaining <= 0) {
                    continue;
                }

                $lessonType = ($sourceSchedule->type === 'lesson') ? 'lesson' : 'exam';
                $slotSize = (int) getSettingValue('duration', $lessonType, $lessonType === 'exam' ? 30 : 50) +
                            (int) getSettingValue('break', $lessonType, $lessonType === 'exam' ? 0 : 10);

                $parentSlots = TimeHelper::calculateItemSlots($dto->startTime, $dto->endTime, $slotSize);
                $slotsToAdd = min($parentSlots, (int) $currentRemaining);
                if ($slotsToAdd < $parentSlots) {
                    $item->end_time = TimeHelper::calculateEndTimeBySlots($dto->startTime, $slotsToAdd, $slotSize, $lessonType);
                }

                $childLessonHoursAdded[$trackingKey] = ($childLessonHoursAdded[$trackingKey] ?? 0) + $slotsToAdd;

                $item->data = array_map(function ($d) use ($childLessonId, $childLesson, $lesson) {
                    $childData = $d;
                    $childData['lesson_id'] = $childLessonId;
                    if (empty($childData['lecturer_id'])) {
                        $childData['lecturer_id'] = $childLesson->lecturer?->id ?? $lesson?->lecturer?->id ?? null;
                    }
                    return $childData;
                }, $dto->data);

                if (!is_array($item->detail)) {
                    $item->detail = [];
                }
                $item->detail['child_lesson_id'] = $childLessonId;
            } else {
                $item->data = $dto->data;
                $item->detail = $dto->detail;
            }

            // Preferred slot parçalama: Yeni item ile örtüşen preferred item'ları
            // parçala/sil ve silinen bölge bilgisini item'ın detail alanına kaydet.
            $displacedInfo = $this->handlePreferredOverlap(
                $targetSchedule->id,
                $dto->dayIndex,
                $dto->weekIndex,
                $item->start_time,
                $item->end_time
            );
            if (!empty($displacedInfo)) {
                $currentDetail = is_array($item->detail) ? $item->detail : [];
                $currentDetail['displaced_preferred'] = $displacedInfo;
                $item->detail = $currentDetail;
            }

            $item->create();

            $breakMinutes = (int) getSettingValue('break', 'lesson', 10);
            $mergedItem = $this->timelineService->mergeAdjacentItems($item, $breakMinutes);

            $createdIds[] = $mergedItem->id;
        }

        return $createdIds;
    }

    /**
     * Group item'ları ilgili tüm schedule'lara kaydeder
     */
    protected function saveGroupItemToSchedules(
        ScheduleItemDTO $dto,
        ?Lesson $lesson,
        Schedule $sourceSchedule,
        array &$affectedScheduleIds = []
    ): array {
        $owners = array_map(function ($o) use ($lesson) {
            if (!isset($o['is_child']) || !$o['is_child']) {
                $o['lesson_context'] = $lesson;
            }
            return $o;
        }, $this->determineOwners($dto, $lesson));
        $createdIds = [];

        $childLessonRemaining = [];
        foreach ($owners as $owner) {
            if (!isset($owner['is_child']) || !$owner['is_child']) {
                continue;
            }
            $clId = $owner['child_lesson_id'];
            if (!isset($childLessonRemaining[$clId])) {
                $childLesson = (new Lesson())->where(['id' => $clId])->with([
                    'lecturer' => ['semester' => $sourceSchedule->semester, 'academic_year' => $sourceSchedule->academic_year]
                ])->first();
                if ($childLesson) {
                    $childLesson->IsScheduleComplete($sourceSchedule->type, $sourceSchedule->semester, $sourceSchedule->academic_year);
                    $childLessonRemaining[$clId] = [
                        'lesson' => $childLesson,
                        'remaining' => (int) ($childLesson->remaining_size ?? 0),
                    ];
                }
            }
        }

        $childSlotsAdded = [];

        foreach ($owners as $owner) {
            /** @var Schedule $targetSchedule */
            $targetSchedule = $this->findOrCreateSchedule(
                $owner,
                $sourceSchedule->academic_year,
                $sourceSchedule->semester,
                $sourceSchedule->type
            );
            $affectedScheduleIds[] = (int) $targetSchedule->id;

            $data = $dto->data;
            $startTime = $dto->startTime;
            $endTime = $dto->endTime;

            if (isset($owner['is_child']) && $owner['is_child']) {
                $childLessonId = $owner['child_lesson_id'];

                if (!isset($childLessonRemaining[$childLessonId])) {
                    continue;
                }

                $childLessonObj = $childLessonRemaining[$childLessonId]['lesson'] ?? null;
                $baseRemaining = $childLessonRemaining[$childLessonId]['remaining'];
                $trackingKey = "{$childLessonId}_{$owner['type']}";
                $alreadyAdded = $childSlotsAdded[$trackingKey] ?? 0;
                $currentRemaining = $baseRemaining - $alreadyAdded;

                if ($currentRemaining <= 0) {
                    continue;
                }

                $lessonType = 'lesson';
                $slotSize = (int) getSettingValue('duration', $lessonType, 50) +
                            (int) getSettingValue('break', $lessonType, 10);

                $parentSlots = TimeHelper::calculateItemSlots($dto->startTime, $dto->endTime, $slotSize);
                $slotsToAdd = min($parentSlots, (int) $currentRemaining);
                if ($slotsToAdd < $parentSlots) {
                    $endTime = TimeHelper::calculateEndTimeBySlots($dto->startTime, $slotsToAdd, $slotSize, $lessonType);
                }

                $childSlotsAdded[$trackingKey] = $alreadyAdded + $slotsToAdd;

                $data = array_map(function ($d) use ($childLessonId, $childLessonObj, $lesson) {
                    $childData = $d;
                    $childData['lesson_id'] = $childLessonId;
                    if (empty($childData['lecturer_id'])) {
                        $childData['lecturer_id'] = $childLessonObj?->lecturer?->id ?? $lesson?->lecturer?->id ?? null;
                    }
                    return $childData;
                }, $dto->data);
            }

            // Preferred slot parçalama (group item): mergeGroupItems öncesinde
            // hedef schedule'daki örtüşen preferred item'ları parçala ve bilgiyi detail'a ekle.
            $groupDetail = $dto->detail ?? [];
            $displacedInfo = $this->handlePreferredOverlap(
                $targetSchedule->id,
                $dto->dayIndex,
                $dto->weekIndex,
                $startTime,
                $endTime
            );
            if (!empty($displacedInfo)) {
                $groupDetail = is_array($groupDetail) ? $groupDetail : [];
                $groupDetail['displaced_preferred'] = $displacedInfo;
            }

            $newIds = $this->mergeGroupItems(
                $targetSchedule->id,
                $dto->dayIndex,
                $dto->weekIndex,
                $startTime,
                $endTime,
                $data,
                $groupDetail
            );

            $createdIds = array_merge($createdIds, $newIds);
        }

        return $createdIds;
    }

    /**
     * Yeni bir item kaydedilmeden önce aynı schedule/gün/hafta üzerindeki
     * çakışan `preferred` item'ları tespit eder, parçalar/siler ve
     * yerinden edilen (displaced) aralıkların bilgisini döndürür.
     *
     * **İşleyiş:**
     * 1. Hedef schedule'da, ilgili gün ve haftada `preferred` statüsündeki
     *    item'ları sorgular.
     * 2. Her biri için yeni item ile örtüşen aralığı (`TimeHelper::getOverlapInterval`)
     *    hesaplar.
     * 3. Örtüşen preferred item'ı `processItemDeletion` aracılığıyla siler/kısaltır.
     *    (Kalan kısımlar otomatik olarak yeni record'lar şeklinde oluşturulur.)
     * 4. Yerinden edilen aralıkları ['start', 'end'] dizisi olarak toplar ve döndürür.
     *
     * Döndürülen dizi, kaydedilecek item'ın `detail['displaced_preferred']` alanına
     * yazılmalıdır; böylece item silindiğinde preferred slot geri oluşturulabilir.
     *
     * @param int    $scheduleId Hedef schedule ID'si
     * @param int    $dayIndex   Gün indisi
     * @param int    $weekIndex  Hafta indisi
     * @param string $startTime  Yeni item'ın başlangıç saati (HH:MM veya HH:MM:SS)
     * @param string $endTime    Yeni item'ın bitiş saati    (HH:MM veya HH:MM:SS)
     * @return array Yerinden edilen preferred aralıkların listesi:
     *               [['start' => 'HH:MM', 'end' => 'HH:MM'], ...]
     *               Çakışan preferred yoksa boş dizi döner.
     */
    private function handlePreferredOverlap(
        int $scheduleId,
        int $dayIndex,
        int $weekIndex,
        string $startTime,
        string $endTime
    ): array {
        // Aynı gün ve haftadaki tüm preferred item'ları getir
        $preferredItems = (new ScheduleItem())
            ->get()
            ->where([
                'schedule_id' => $scheduleId,
                'day_index'   => $dayIndex,
                'week_index'  => $weekIndex,
                'status'      => ScheduleItemStatus::PREFERRED->value,
            ])
            ->all();

        if (empty($preferredItems)) {
            return [];
        }

        $displacedIntervals = [];

        foreach ($preferredItems as $preferred) {
            $overlap = TimeHelper::getOverlapInterval(
                $preferred->start_time,
                $preferred->end_time,
                $startTime,
                $endTime
            );

            if ($overlap === null) {
                // Bu preferred item ile gerçek bir örtüşme yok
                continue;
            }

            // Yerinden edilen aralığı kaydet
            $displacedIntervals[] = [
                'start' => $overlap['start'],
                'end'   => $overlap['end'],
            ];

            $this->logger->debug('Preferred slot parçalanıyor', $this->logContext([
                'preferred_item_id' => $preferred->id,
                'preferred_range'   => $preferred->getShortStartTime() . '-' . $preferred->getShortEndTime(),
                'overlap_range'     => $overlap['start'] . '-' . $overlap['end'],
            ]));

            // Preferred item'ı sil ve kalan kısımlarını (overlap dışındaki bölgeler)
            // doğrudan yeni record'lar olarak oluştur.
            // NOT: processItemDeletion burada kullanılmaz çünkü preferred item'lar
            // slot/break kavramı taşımaz; duration=0 ile getCriticalPoints sonsuz döngüye
            // girebilir. Manuel parçalama daha güvenlidir.
            $preferred->delete();

            $prefStart = $preferred->getShortStartTime();
            $prefEnd   = $preferred->getShortEndTime();

            // Sol parça: preferred başlangıcı → overlap başlangıcı (varsa)
            if ($prefStart < $overlap['start']) {
                $leftItem = new ScheduleItem();
                $leftItem->schedule_id = $preferred->schedule_id;
                $leftItem->day_index   = $preferred->day_index;
                $leftItem->week_index  = $preferred->week_index;
                $leftItem->start_time  = $prefStart;
                $leftItem->end_time    = $overlap['start'];
                $leftItem->status      = ScheduleItemStatus::PREFERRED->value;
                $leftItem->data        = null;
                $leftItem->detail      = null;
                $leftItem->create();
            }

            // Sağ parça: overlap bitişi → preferred bitişi (varsa)
            if ($overlap['end'] < $prefEnd) {
                $rightItem = new ScheduleItem();
                $rightItem->schedule_id = $preferred->schedule_id;
                $rightItem->day_index   = $preferred->day_index;
                $rightItem->week_index  = $preferred->week_index;
                $rightItem->start_time  = $overlap['end'];
                $rightItem->end_time    = $prefEnd;
                $rightItem->status      = ScheduleItemStatus::PREFERRED->value;
                $rightItem->data        = null;
                $rightItem->detail      = null;
                $rightItem->create();
            }
        }

        return $displacedIntervals;
    }

    /**
     * Ders programı öğelerini siler, zaman çizelgesini dilimler ve kalan parçaları korur.
     *
     * @param ScheduleItemDTO[] $dtos Silinecek item DTO'ları
     * @param bool $expandGroup Grup derslerinin tüm şubelerini kapsasın mı
     * @return DeleteScheduleResult
     * @throws Exception
     */
    public function deleteScheduleItems(
        array $dtos,
        bool $expandGroup = true
    ): DeleteScheduleResult {
        $this->logger->debug("LessonScheduleService::deleteScheduleItems START", $this->logContext(['count' => count($dtos)]));

        try {
            return Database::transaction(function () use ($dtos, $expandGroup) {
                $processedSiblingIds = [];
                $deletedIds = [];
                $createdItemIds = [];
                $affectedScheduleIds = [];

                $duration = (int) getSettingValue('duration', 'lesson', 50);
                $break = (int) getSettingValue('break', 'lesson', 10);

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

                    $baseLessonIds = [];
                    foreach ($scheduleItem->getSlotDatas() as $sd) {
                        if ($sd->lesson) {
                            $baseLessonIds[] = (int) $sd->lesson->id;
                        }
                    }

                    $siblings = $this->findSiblingItems($scheduleItem, $baseLessonIds);
                    $siblingIds = array_map(fn($s) => (int) $s->id, $siblings);

                    $rawIntervals = [];
                    $targetLessonIds = [];

                    foreach ($dtos as $reqDto) {
                        if (in_array((int) $reqDto->id, $siblingIds)) {
                            $rawIntervals[] = [
                                'start' => substr($reqDto->startTime ?? $scheduleItem->start_time, 0, 5),
                                'end' => substr($reqDto->endTime ?? $scheduleItem->end_time, 0, 5)
                            ];

                            if (!empty($reqDto->data)) {
                                foreach ($reqDto->data as $d) {
                                    if (isset($d['lesson_id'])) {
                                        $lId = (int) $d['lesson_id'];
                                        if (!in_array($lId, $targetLessonIds)) {
                                            $targetLessonIds[] = $lId;

                                            if ($expandGroup) {
                                                $lObj = (new Lesson())
                                                    ->where(['id' => $lId])
                                                    ->with(['childLessons', 'parentLesson'])
                                                    ->first();

                                                if ($lObj) {
                                                    if (!empty($lObj->parentLesson)) {
                                                        $parentLessonId = (int) $lObj->parentLesson->id;
                                                        if (!in_array($parentLessonId, $targetLessonIds)) {
                                                            $targetLessonIds[] = $parentLessonId;
                                                        }

                                                        $parentObj = (new Lesson())
                                                            ->where(['id' => $parentLessonId])
                                                            ->with(['childLessons'])
                                                            ->first();

                                                        if ($parentObj) {
                                                            foreach ($parentObj->childLessons as $cl) {
                                                                if (!in_array((int) $cl->id, $targetLessonIds)) {
                                                                    $targetLessonIds[] = (int) $cl->id;
                                                                }
                                                            }
                                                        }
                                                    } elseif (!empty($lObj->childLessons)) {
                                                        foreach ($lObj->childLessons as $cl) {
                                                            if (!in_array((int) $cl->id, $targetLessonIds)) {
                                                                $targetLessonIds[] = (int) $cl->id;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    usort($rawIntervals, fn($a, $b) => strcmp($a['start'], $b['start']));
                    $mergedIntervals = [];

                    foreach ($rawIntervals as $interval) {
                        if (empty($mergedIntervals)) {
                            $mergedIntervals[] = $interval;
                        } else {
                            $lastIdx = count($mergedIntervals) - 1;
                            $lastEnd = $mergedIntervals[$lastIdx]['end'];
                            $gapMinutes = (strtotime($interval['start']) - strtotime($lastEnd)) / 60;

                            if ($gapMinutes >= 0 && $gapMinutes <= $break) {
                                $mergedIntervals[$lastIdx]['end'] = max($mergedIntervals[$lastIdx]['end'], $interval['end']);
                            } else {
                                $mergedIntervals[] = $interval;
                            }
                        }
                    }

                    if (empty($mergedIntervals)) {
                        continue;
                    }

                    foreach ($siblings as $sibling) {
                        if ($sibling->schedule_id) {
                            $affectedScheduleIds[] = (int) $sibling->schedule_id;
                        }
                        $sibling->delete();
                        $deletedIds[] = $sibling->id;
                    }

                    foreach ($siblings as $sibling) {
                        $result = $this->processItemDeletion(
                            $sibling,
                            $mergedIntervals,
                            $targetLessonIds,
                            $duration,
                            $break,
                            false
                        );

                        if (!empty($result['created'])) {
                            foreach ($result['created'] as $createdItem) {
                                $createdItemIds[] = $createdItem->id;
                                if ($createdItem->schedule_id) {
                                    $affectedScheduleIds[] = (int) $createdItem->schedule_id;
                                }
                            }
                        }
                    }

                    $processedSiblingIds = array_unique(array_merge($processedSiblingIds, $siblingIds));
                }

                // Etkilenen tüm schedule'ların updated_at zamanını güncelle
                if (!empty($affectedScheduleIds)) {
                    $this->touchSchedules($affectedScheduleIds);
                }

                $this->logger->debug(
                    "LessonScheduleService::deleteScheduleItems SUCCESS: " . count($deletedIds) . " silindi, " . count($createdItemIds) . " oluşturuldu",
                    $this->logContext(['deletedIds' => $deletedIds, 'createdIds' => $createdItemIds, 'affected_schedule_count' => count(array_unique($affectedScheduleIds))])
                );

                return DeleteScheduleResult::success($deletedIds, $createdItemIds);
            });
        } catch (Exception $e) {
            $this->logger->error(
                "LessonScheduleService::deleteScheduleItems ERROR: " . $e->getMessage(),
                $this->logContext(['exception' => $e])
            );

            throw $e;
        }
    }

    /**
     * Item parçalama (flatten timeline logic)
     *
     * @param ScheduleItem $item Item
     * @param array $deleteIntervals Silme aralıkları [['start' => '09:00', 'end' => '10:00'], ...]
     * @param array $targetLessonIds Silinecek ders ID'leri (boşsa tümü)
     * @param int $duration Ders süresi (dakika)
     * @param int $break Teneffüs süresi (dakika)
     * @param bool $deleteOriginal Original item'ı sil mi?
     * @return array ['deleted' => bool, 'created' => ScheduleItem[]]
     */
    protected function processItemDeletion(
        ScheduleItem $item,
        array $deleteIntervals,
        array $targetLessonIds = [],
        int $duration = 50,
        int $break = 10,
        bool $deleteOriginal = true
    ): array {
        $startStr = $item->getShortStartTime();
        $endStr = $item->getShortEndTime();

        // 1. Kritik noktaları topla (Zaman çizelgesini düzleştir)
        $internalPoints = [];
        foreach ($deleteIntervals as $del) {
            $internalPoints[] = $del['start'];
            $internalPoints[] = $del['end'];
        }

        $points = $this->timelineService->getCriticalPoints($startStr, $endStr, $internalPoints, $duration, $break);

        $dataList = $item->data ?: [];

        // 2. Dilimler (segments) üzerinden geç
        $segments = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $pStart = $points[$i];
            $pEnd = $points[$i + 1];

            // Bu dilim silinecek mi?
            $isDeleteZone = false;
            foreach ($deleteIntervals as $del) {
                if ($del['start'] <= $pStart && $del['end'] >= $pEnd) {
                    $isDeleteZone = true;
                    break;
                }
            }

            $currentData = $dataList;
            if ($isDeleteZone) {
                if (!empty($targetLessonIds)) {
                    $currentData = array_values(array_filter($dataList, function ($l) use ($targetLessonIds) {
                        return !in_array((int) $l['lesson_id'], $targetLessonIds);
                    }));
                } else {
                    $currentData = [];
                }
            }

            $isSpecial = in_array($item->status, ['preferred', 'unavailable']);
            $wasPreferred = ($item->detail['preferred'] ?? false);

            $shouldKeep = true;
            if (empty($currentData)) {
                $shouldKeep = $isSpecial ? !$isDeleteZone : ($wasPreferred && $isDeleteZone);
            }

            $segments[] = [
                'start' => $pStart,
                'end' => $pEnd,
                'data' => $currentData,
                'detail' => $item->detail,
                'isBreak' => (TimeHelper::getDurationMinutes($pStart, $pEnd) == $break),
                'shouldKeep' => $shouldKeep
            ];
        }

        // 3. & 4. Birleştirme ve Temizlik
        $newSegments = $this->timelineService->mergeContiguousSegments($segments, $break);

        // 5. Veritabanı güncelleme
        if ($deleteOriginal) {
            $item->delete();
        }

        $createdItems = [];
        if (!empty($newSegments)) {
            foreach ($newSegments as $seg) {
                $newItem = new ScheduleItem();
                $newItem->schedule_id = $item->schedule_id;
                $newItem->day_index = $item->day_index;
                $newItem->week_index = $item->week_index;
                $newItem->start_time = $seg['start'];
                $newItem->end_time = $seg['end'];

                $newItem->status = $this->timelineService->determineStatus(
                    $seg['data'],
                    $item->status,
                    $item->detail['preferred'] ?? false
                );

                $newItem->data = $seg['data'];
                $segDetail = is_array($item->detail) ? $item->detail : [];
                unset($segDetail['displaced_preferred']);
                $newItem->detail = !empty($segDetail) ? $segDetail : null;
                $newItem->create();
                $createdItems[] = $newItem;
            }
        }

        return [
            'deleted' => $deleteOriginal,
            'created' => $createdItems
        ];
    }
}

