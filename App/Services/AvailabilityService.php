<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use Exception;
use function App\Helpers\getSettingValue;

/**
 * Ders ve Sınav programlarında müsait derslik ve gözetmen sorgulama servisi.
 *
 * availableClassrooms: Ders ve sınav için ortak kullanılır;
 *   iç mantık schedule.type'a göre ders/sınav filtresi uygular.
 */
class AvailabilityService extends BaseService
{
    /**
     * Belirtilen filtrelere uygun dersliklerin listesini döndürür.
     *
     * Ders programı → dersin classroom_type değerine uygun sınıflar.
     * Sınav programı → UZEM (type=3) hariç tüm sınıflar.
     *
     * @param array $filters Validated filtreler:
     *   schedule_id, lesson_id, day_index, week_index, items (JSON)
     * @return Classroom[] Müsait derslik nesneleri
     * @throws Exception
     */
    public function availableClassrooms(array $filters = []): array
    {
        $schedule = (new Schedule())
            ->where(["id" => $filters['schedule_id']])
            ->with("items")
            ->first()
            ?: throw new Exception("Uygun derslikleri belirlemek için Program bulunamadı");

        $lesson = (new Lesson())->find($filters['lesson_id'])
            ?: throw new Exception("Derslik türünü belirlemek için ders bulunamadı");

        $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];

        if (in_array($schedule->type, $examTypes)) {
            // Sınav → UZEM (type=3) hariç tüm derslikler
            $classrooms = (new Classroom())->get()->where(["type" => ['!=' => 3]])->all();
        } else {
            // Ders → classroom_type ile eşleşen derslikler (Karma=4 ise Lab+Derslik)
            $classroom_type = $lesson->classroom_type == 4 ? [1, 2] : [$lesson->classroom_type];
            $classrooms = (new Classroom())->get()->where(["type" => ['in' => $classroom_type]])->all();
        }

        $itemsToCheck = json_decode($filters['items'] ?? '[]', true) ?: [];
        $availableClassrooms = [];

        foreach ($classrooms as $classroom) {
            $classroomSchedule = (new Schedule())->firstOrCreate([
                'type' => $schedule->type,
                'owner_type' => 'classroom',
                'owner_id' => $classroom->id,
                'semester_no' => null,
                'semester' => $schedule->semester,
                'academic_year' => $schedule->academic_year,
            ]);

            $existingItems = (new ScheduleItem())->get()->where([
                'schedule_id' => $classroomSchedule->id,
                'day_index' => $filters['day_index'],
                'week_index' => $filters['week_index'],
            ])->all();

            $isAvailable = true;

            // UZEM sınıfları her zaman uygun sayılır
            if ($classroom->type != 3) {
                foreach ($itemsToCheck as $checkItem) {
                    foreach ($existingItems as $existingItem) {
                        if (
                            $this->checkTimeOverlap(
                                $checkItem['start_time'],
                                $checkItem['end_time'],
                                $existingItem->start_time,
                                $existingItem->end_time
                            )
                        ) {
                            $isAvailable = false;
                            break 2;
                        }
                    }
                }
            }

            if ($isAvailable) {
                $availableClassrooms[] = $classroom;
            }
        }

        return $availableClassrooms;
    }

    /**
     * İki zaman aralığının çakışıp çakışmadığını kontrol eder.
     * (Start1 < End2) && (Start2 < End1)
     */
    private function checkTimeOverlap(
        string $start1,
        string $end1,
        string $start2,
        string $end2
    ): bool {
        return ($start1 < $end2) && ($start2 < $end1);
    }
}
