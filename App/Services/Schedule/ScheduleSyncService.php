<?php

namespace App\Services\Schedule;

use App\Services\BaseService;
use App\Enums\ExamType;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Enums\OwnerType;
use App\Enums\ScheduleItemStatus;
use Exception;
use function App\Helpers\getSettingValue;
use App\Repositories\ScheduleRepository;

/**
 * Ders ve Sınav birleştirme işlemlerinde program (schedule) senkronizasyonunu yönetir.
 */
class ScheduleSyncService extends BaseService
{
    private ScheduleService $scheduleService;
    private ScheduleRepository $scheduleRepo;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleService = new ScheduleService();
        $this->scheduleRepo = new ScheduleRepository();
    }

    /**
     * Parent dersin sınav schedule item'larını child için kopyalar.
     * Sadece ders ve program owner'larına kopyalama yapar.
     * Gözetmen/derslik atamaları kopyalanmaz — ExamService::saveExamScheduleItems
     * zaten exam child'ların owner'larını da kaydeder.
     *
     * @param Lesson $parentLesson
     * @param Lesson $childLesson
     * @throws Exception
     */
    public function syncExamChildFromParent(Lesson $parentLesson, Lesson $childLesson): void
    {
        $examTypes = ExamType::values();

        // Parent'ın sınav schedule'larını bul
        $parentExamSchedules = (new Schedule())->get()->where([
            'owner_type' => OwnerType::LESSON->value,
            'owner_id' => $parentLesson->id,
            'type' => ['in' => $examTypes]
        ])->with(['items'])->all();

        if (empty($parentExamSchedules)) {
            return;
        }

        foreach ($parentExamSchedules as $parentSchedule) {
            if (empty($parentSchedule->items)) {
                continue;
            }

            // Child ders ve program owner'ları
            $owners = [
                ['type' => 'lesson', 'id' => $childLesson->id, 'semester_no' => null],
                ['type' => 'program', 'id' => $childLesson->program_id, 'semester_no' => $childLesson->semester_no],
            ];

            foreach ($parentSchedule->items as $item) {
                // Sadece program/ders item'larını kopyala (gözetmen/derslik atamaları hariç)
                $detail = $item->detail;
                if (isset($detail['reference_type']) && $detail['reference_type'] === 'exam_assignment') {
                    continue;
                }

                foreach ($owners as $owner) {
                    if (!$owner['id']) {
                        continue;
                    }

                    $scheduleFilters = [
                        'owner_type' => $owner['type'],
                        'owner_id' => $owner['id'],
                        'semester' => $parentSchedule->semester,
                        'academic_year' => $parentSchedule->academic_year,
                        'type' => $parentSchedule->type,
                        'semester_no' => $owner['type'] === 'program' ? $owner['semester_no'] : null,
                    ];

                    $childSchedule = clone $this->scheduleRepo->findOrCreate($scheduleFilters);

                    $newItem = new ScheduleItem();
                    $newItem->schedule_id = $childSchedule->id;
                    $newItem->day_index = $item->day_index;
                    $newItem->week_index = $item->week_index;
                    $newItem->start_time = $item->start_time;
                    $newItem->end_time = $item->end_time;
                    $newItem->status = $item->status;
                    $newItem->data = [
                        [
                            'lesson_id' => $childLesson->id,
                            'lecturer_id' => null,
                            'classroom_id' => null,
                        ]
                    ];
                    $newItem->detail = $item->detail;
                    $newItem->create();
                }
            }
        }

        $this->logger->info('Sınav programı child\'a kopyalandı', [
            'parent_id' => $parentLesson->id,
            'child_id' => $childLesson->id,
        ]);
    }

    /**
     * Parent dersin schedule item'larını child için kopyalar.
     * Belirli slot'lar harici tutulabilir; saat aralıklı item'lar bireysel 1-saatlik item'lara ayrılır.
     *
     * @param Lesson $parentLesson
     * @param Lesson $childLesson
     * @param array  $slotsToSkip Kopyalanmayacak slotlar [item_id => [slot_index, ...]]
     * @throws Exception
     */
    public function syncChildScheduleFromParent(Lesson $parentLesson, Lesson $childLesson, array $slotsToSkip = []): void
    {
        /** @var Schedule $parentSchedule */
        $parentSchedule = (new Schedule())
            ->get()
            ->where(['owner_type' => OwnerType::LESSON->value, 'owner_id' => $parentLesson->id])
            ->with(['items'])
            ->first();

        if (!$parentSchedule) {
            return;
        }

        $duration = (int) getSettingValue('duration', 'lesson', 50);
        $break    = (int) getSettingValue('break', 'lesson', 10);

        foreach ($parentSchedule->items as $item) {
            $skippedSlots = $slotsToSkip[$item->id] ?? [];

            if (empty($skippedSlots)) {
                // Hiç slot silinmiyor — item'ı olduğu gibi kopyala (mevcut davranış)
                $this->copyItemToOwners($parentSchedule, $item, $this->buildChildItemData($item, $parentLesson, $childLesson), $childLesson);
                continue;
            }

            // Bazı slotlar silinecek — item'ı bireysel 1-saatlik parçalara ayır, seçilenleri atla
            $start = \DateTime::createFromFormat('H:i:s', $item->start_time)
                  ?: \DateTime::createFromFormat('H:i', $item->start_time);
            if (!$start) continue;

            $slotStart = clone $start;
            $slotIndex = 0;

            while (true) {
                $slotEnd = clone $slotStart;
                $slotEnd->modify("+{$duration} minutes");

                if (!in_array($slotIndex, $skippedSlots)) {
                    // Bu slot kopyalanacak: yeni baş/bitiş zamanlarıyla tek item oluştur
                    $partialItem = clone $item;
                    $partialItem->id         = null; // yeni kayıt
                    $partialItem->start_time = $slotStart->format('H:i:s');
                    $partialItem->end_time   = $slotEnd->format('H:i:s');
                    $this->copyItemToOwners($parentSchedule, $partialItem, $this->buildChildItemData($item, $parentLesson, $childLesson), $childLesson);
                }

                $slotStart = clone $slotEnd;
                $slotStart->modify("+{$break} minutes");
                $slotIndex++;

                $itemEnd = \DateTime::createFromFormat('H:i:s', $item->end_time)
                        ?: \DateTime::createFromFormat('H:i', $item->end_time);
                if (!$itemEnd || $slotStart >= $itemEnd) break;
            }
        }
    }

    /**
     * Tek bir schedule item'ı child ders için gerekli owner'lara kopyalar.
     */
    private function copyItemToOwners(Schedule $parentSchedule, ScheduleItem $item, array $itemData, Lesson $childLesson): void
    {
        $owners = [
            ['type' => 'lesson', 'id' => $childLesson->id, 'semester_no' => null],
            ['type' => 'program', 'id' => $childLesson->program_id, 'semester_no' => $childLesson->semester_no],
        ];

        foreach ($owners as $owner) {
            if (!$owner['id']) {
                continue;
            }

            $scheduleFilters = [
                'owner_type'   => $owner['type'],
                'owner_id'     => $owner['id'],
                'semester'     => $parentSchedule->semester,
                'academic_year' => $parentSchedule->academic_year,
                'type'         => $parentSchedule->type,
                'semester_no'  => $owner['type'] === 'program' ? $owner['semester_no'] : null,
            ];

            $childSchedule = clone $this->scheduleRepo->findOrCreate($scheduleFilters);

            $newItem = new ScheduleItem();
            $newItem->schedule_id = $childSchedule->id;
            $newItem->day_index   = $item->day_index;
            $newItem->start_time  = $item->start_time;
            $newItem->end_time    = $item->end_time;
            $newItem->status      = $item->status;
            $newItem->data        = $itemData;
            $newItem->detail      = $item->detail;
            $newItem->create();
        }
    }

    /**
     * Bir schedule item için child'a ait data dizisini oluşturur.
     * Group item'larda parent'ın lesson_id'sine ait slot bulunur, diğerlerinde ilk slot kullanılır.
     *
     * @param ScheduleItem $item
     * @param Lesson       $parentLesson
     * @param Lesson       $childLesson
     * @return array
     */
    private function buildChildItemData(ScheduleItem $item, Lesson $parentLesson, Lesson $childLesson): array
    {
        $itemData = [['lesson_id' => null, 'lecturer_id' => null, 'classroom_id' => null]];

        if ($item->status === ScheduleItemStatus::GROUP->value) {
            foreach ($item->getSlotDatas() as $slotData) {
                if ($slotData->lesson_id == $parentLesson->id) {
                    $itemData[0] = [
                        'lesson_id' => $childLesson->id,
                        'lecturer_id' => $childLesson->lecturer_id,
                        'classroom_id' => $slotData->classroom->id,
                    ];
                    break;
                }
            }
        } else {
            $slotData = $item->getSlotDatas()[0];
            $itemData[0] = [
                'lesson_id' => $childLesson->id,
                'lecturer_id' => $childLesson->lecturer_id,
                'classroom_id' => $slotData->classroom->id,
            ];
        }

        return $itemData;
    }
}
