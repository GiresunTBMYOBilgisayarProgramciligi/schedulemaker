<?php

namespace App\Services\Schedule;

use App\Core\Database;
use App\DTOs\ScheduleItemDTO;
use App\Exceptions\ValidationException;
use App\Helpers\TimeHelper;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\DTOs\SaveScheduleResult;
use App\Enums\ScheduleItemStatus;
use App\Enums\OwnerType;
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
                foreach ($dtos as $index => $dto) {
                    $this->logger->debug("Processing item #$index", $this->logContext(['itemData' => $dto->toArray()]));

                    // İlgili bilgileri al
                    /** @var Schedule $schedule */
                    $schedule = $this->scheduleRepo->find($dto->scheduleId);
                    if (!$schedule) {
                        throw new Exception("Schedule not found: {$dto->scheduleId}");
                    }

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
                        $itemIds = $this->saveGroupItemToSchedules($dto, $lesson, $schedule);
                    } else {
                        // SINGLE/DUMMY ITEM
                        // MULTI-SCHEDULE KAYDETME: Tüm ilgili schedule'lara kaydet
                        $itemIds = $this->saveToMultipleSchedules($dto, $lesson, $schedule);
                    }

                    $createdIds = array_merge($createdIds, $itemIds);

                    $this->logger->debug("Item #{$index} saved to " . count($itemIds) . " schedules", $this->logContext([
                        'item_ids' => $itemIds,
                        'is_group' => $isGroup
                    ]));

                    // Etkilenen ders ID'lerini kaydet
                    if (!$isDummy && $lesson) {
                        $affectedLessonIds[] = $lesson->id;
                    }
                }

                // Ders saati kontrolü
                if (!empty($affectedLessonIds)) {
                    $this->checkLessonHourLimits(array_unique($affectedLessonIds), $schedule->type);
                }

                $this->logger->info("Schedule items saved successfully", $this->logContext([
                    'created_count' => count($createdIds),
                    'schedule_id' => $dtos[0]->scheduleId ?? null
                ]));

                return SaveScheduleResult::success($createdIds, count($dtos));
            });
        } catch (Exception $e) {
            $this->logger->error("Failed to save schedule items: " . $e->getMessage(), $this->logContext([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            throw $e;
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

        return Database::transaction(function () use ($dtos, $deletedDtos) {
            // Önce silinecek öğeleri sil
            if (!empty($deletedDtos)) {
                $this->deleteScheduleItems($deletedDtos);
            }
            
            // Sonra yeni öğeleri kaydet (çakışma kontrolü burada yapılıyor ve silinmiş öğeleri görmeyecek)
            return $this->saveScheduleItems($dtos);
        });
    }

    /**
     * Tekil item'ı ilgili tüm schedule'lara kaydeder
     */
    protected function saveToMultipleSchedules(
        ScheduleItemDTO $dto,
        ?Lesson $lesson,
        Schedule $sourceSchedule
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
            $childLesson = (new Lesson())->find($childLessonId);
            if ($childLesson) {
                $childLesson->IsScheduleComplete($sourceSchedule->type);
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

                $item->data = array_map(function ($d) use ($childLessonId) {
                    $childData = $d;
                    $childData['lesson_id'] = $childLessonId;
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
            $createdIds[] = $item->id;
        }

        return $createdIds;
    }

    /**
     * Group item'ları ilgili tüm schedule'lara kaydeder
     */
    protected function saveGroupItemToSchedules(
        ScheduleItemDTO $dto,
        ?Lesson $lesson,
        Schedule $sourceSchedule
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
                $childLesson = (new Lesson())->find($clId);
                if ($childLesson) {
                    $childLesson->IsScheduleComplete($sourceSchedule->type);
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

            $data = $dto->data;
            $startTime = $dto->startTime;
            $endTime = $dto->endTime;

            if (isset($owner['is_child']) && $owner['is_child']) {
                $childLessonId = $owner['child_lesson_id'];

                if (!isset($childLessonRemaining[$childLessonId])) {
                    continue;
                }

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

                $data = array_map(function ($d) use ($childLessonId) {
                    $childData = $d;
                    $childData['lesson_id'] = $childLessonId;
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
}

