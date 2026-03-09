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
     * Ders programı tamamlanmamış olan derslerin bilgilerini döner.
     *
     * @param Schedule $schedule
     * @param bool $preferenceMode
     * @return array
     * @throws Exception
     */
    public function availableLessons(Schedule $schedule, bool $preferenceMode = false): array
    {
        if ($preferenceMode && in_array($schedule->owner_type, ['user', 'classroom', 'lesson'])) {
            // Sadece tercih modunda Preferred ve Unavailable kartlarını döndür
            return [
                (object) [
                    'id' => 'dummy-preferred',
                    'name' => '',
                    'code' => 'PREF',
                    'status' => 'preferred',
                    'hours' => 1,
                    'lecturer_id' => $schedule->owner_id, // Context hoca ise hoca ID'si
                    'is_dummy' => true
                ],
                (object) [
                    'id' => 'dummy-unavailable',
                    'name' => '',
                    'code' => 'UNAV',
                    'status' => 'unavailable',
                    'hours' => 1,
                    'lecturer_id' => $schedule->owner_id,
                    'is_dummy' => true
                ]
            ];
        }

        $available_lessons = [];

        $lessonFilters = [
            'semester' => $schedule->semester,
            'academic_year' => $schedule->academic_year,
            '!type' => 4 // staj dersleri dahil değil
        ];

        if ($schedule->owner_type == "program") {
            $lessonFilters = array_merge($lessonFilters, [
                'program_id' => $schedule->owner_id,
            ]);
        } elseif ($schedule->owner_type == "classroom") {
            $classroom = (new Classroom())->find($schedule->owner_id);
            $lessonFilters = array_merge($lessonFilters, [
                'classroom_type' => $classroom->type,
            ]);
        } elseif ($schedule->owner_type == "user") {
            $lessonFilters = array_merge($lessonFilters, [
                'lecturer_id' => $schedule->owner_id,
            ]);
            unset($lessonFilters["!type"]); // staj derslerini dahil et
        } elseif ($schedule->owner_type == "lesson") {
            $lessonFilters = array_merge($lessonFilters, [
                'id' => $schedule->owner_id,
            ]);
        }

        // Eğer program schedule'ı ise semester_no filtresini ekle
        if ($schedule->semester_no !== null) {
            $lessonFilters['semester_no'] = $schedule->semester_no;
        }
        $lessonsList = (new Lesson())->get()->where($lessonFilters)->with(['lecturer', 'program'])->all();
        $this->logger->debug("availableLessons found " . count($lessonsList) . " potential lessons for schedule " . $schedule->id, $this->logContext());

        /**
         * Programa ait tüm derslerin program tamamlanma durumları kontrol ediliyor.
         * @var Lesson $lesson
         */
        foreach ($lessonsList as $lesson) {
            $isComplete = $lesson->IsScheduleComplete($schedule->type);
            if (!$isComplete) {
                // Ders Programı tamamlanmamışsa

                if ($schedule->type == 'lesson') {
                    $lesson->hours -= $lesson->placed_hours; // kalan saat dersin saati olarak güncelleniyor
                } elseif (in_array($schedule->type, ['midterm-exam', 'final-exam', 'makeup-exam'])) {
                    $lesson->size = $lesson->remaining_size; // kalan mevcut dersin mevcudu olarak güncelleniyor
                }

                $available_lessons[] = $lesson;
            }
        }

        return $available_lessons;
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
