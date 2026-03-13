<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use DateTime;
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
        if ($preferenceMode) {
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
        $lessonsList = (new Lesson())->get()->where($lessonFilters)->with(['lecturer', 'program','childLessons'])->all();
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
        // uygun dersler belirlendikten sonra sınav programında gruplu dersleri birleştirmek için yapılan işlem
        if (in_array($schedule->type, ['midterm-exam', 'final-exam', 'makeup-exam'])) {
            $available_lessons = $this->groupExamLessons($available_lessons, $schedule->type);
        }

        return $available_lessons;
    }

    /**
     * Sınav programı için gruplu dersleri (aynı kod, farklı grup) tek bir ders olarak birleştirir.
     * Çocuk dersleri (parent-child/birleştirilmiş) olan ana derslerin mevcutları,
     * çocuk derslerin kalan mevcutlarıyla toplanarak doğru toplam üzerinden gözetmen ataması sağlanır.
     *
     * @param array $lessons
     * @param string $scheduleType Sınav tipi (midterm-exam, final-exam, makeup-exam)
     * @return array
     */
    private function groupExamLessons(array $lessons, string $scheduleType = 'midterm-exam'): array
    {
        $grouped = [];
        $result = [];

        foreach ($lessons as $lesson) {
            // Çocuk dersleri varsa, hangi programlara ait olduklarını ders adına ekle (gösterim amaçlı).
            // NOT: Ana dersin remaining_size'ı IsScheduleComplete() tarafından getLinkedLessonIds()
            // üzerinden hesaplandığından çocuk dersler zaten dahildir — ayrıca size eklenmez.
            if (!empty($lesson->childLessons)) {
                $mergedPrograms = [];
                foreach ($lesson->childLessons as $childLesson) {
                    if ($childLesson->program) {
                        $mergedPrograms[] = $childLesson->program->name;
                    }
                }
                // Birleştirilen programları ders adına ekle (gösterim amaçlı)
                if (!empty($mergedPrograms)) {
                    $lesson->name .= ' [' . implode(', ', $mergedPrograms) . ']';
                }
            }

            // group_no > 0 olanları kod bazlı grupla
            if ($lesson->group_no > 0) {
                $grouped[$lesson->code][] = $lesson;
            } else {
                $result[] = $lesson;
            }
        }

        foreach ($grouped as $code => $groupLessons) {
            if (count($groupLessons) <= 1) {
                $result = array_merge($result, $groupLessons);
                continue;
            }

            // Birden fazla grup varsa birleştir
            $representative = $groupLessons[0];
            $groupNumbers = [];

            foreach ($groupLessons as $l) {
                $groupNumbers[] = $l->group_no;
            }

            sort($groupNumbers);
            // İsim güncelleme (Sadece gösterim amaçlı)
            $representative->name .= " (Grup " . implode(", ", $groupNumbers) . ")";

            $result[] = $representative;
        }

        return $result;
    }

    /**
     * Hocanın tercih ettiği ve engellediği saat bilgilerini döner
     *
     * @param array $filters Validated filtreler:
     *   lesson_id, type, semester, academic_year, week_index
     * @return array [unavailableCells => ..., preferredCells => ...]
     * @throws Exception
     */
    public function getLecturerAvailability(array $filters): array
    {
        $lesson = (new Lesson())->where(['id' => $filters['lesson_id']])->with(['lecturer'])->first()
            ?: throw new Exception("Ders bulunamadı");
        $lecturer = $lesson->lecturer;

        $slots = $this->getTimeSlots($filters['type']);
        $unavailableCells = [];
        $preferredCells = [];

        $schedules = (new Schedule())->get()->where([
            'owner_type' => 'user',
            'owner_id' => $lecturer->id,
            'type' => $filters['type'],
            'semester' => $filters['semester'],
            'academic_year' => $filters['academic_year'],
        ])->with(['items'])->all();

        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where([
                'schedule_id' => $schedule->id,
                'week_index' => $filters['week_index']
            ])->all();

            foreach ($items as $item) {
                $itemStart = substr($item->start_time, 0, 5);
                $itemEnd = substr($item->end_time, 0, 5);

                foreach ($slots as $rowIndex => $slot) {
                    if ($this->checkTimeOverlap($itemStart, $itemEnd, $slot['start'], $slot['end'])) {
                        if ($item->status === 'preferred') {
                            $preferredCells[$rowIndex + 1][$item->day_index + 1] = true;
                        } else {
                            $unavailableCells[$rowIndex + 1][$item->day_index + 1] = true;
                        }
                    }
                }
            }
        }

        return [
            "unavailableCells" => $unavailableCells,
            "preferredCells" => $preferredCells
        ];
    }

    /**
     * Dersliklerin doluluk durumuna göre müsait olmayan hücreleri döner.
     *
     * @param array $filters Validated filtreler:
     *   lesson_id, type, semester, academic_year, week_index
     * @return array [unavailableCells => ...]
     * @throws Exception
     */
    public function getClassroomAvailability(array $filters): array
    {
        $lesson = (new Lesson())->find($filters['lesson_id']) ?: throw new Exception("Ders bulunamadı");
        $classroom_type = $lesson->classroom_type == 4 ? [1, 2] : [$lesson->classroom_type];
        $classrooms = (new Classroom())->get()->where(['type' => ['in' => $classroom_type]])->all();

        $slots = $this->getTimeSlots($filters['type']);
        $type = in_array($filters['type'], ['midterm-exam', 'final-exam', 'makeup-exam']) ? 'exam' : 'lesson';
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);

        $classroomOccupancy = [];
        $classroomIds = array_column($classrooms, 'id');
        $classroomTypes = [];
        foreach ($classrooms as $c) {
            $classroomTypes[$c->id] = (int) $c->type;
        }

        $schedules = (new Schedule())->get()->where([
            'owner_type' => 'classroom',
            'owner_id' => ['in' => $classroomIds],
            'type' => $filters['type'],
            'semester' => $filters['semester'],
            'academic_year' => $filters['academic_year'],
        ])->all();

        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where([
                'schedule_id' => $schedule->id,
                'week_index' => $filters['week_index']
            ])->all();

            foreach ($items as $item) {
                $itemStart = substr($item->start_time, 0, 5);
                $itemEnd = substr($item->end_time, 0, 5);

                foreach ($slots as $rowIndex => $slot) {
                    if ($this->checkTimeOverlap($itemStart, $itemEnd, $slot['start'], $slot['end'])) {
                        if (isset($classroomTypes[$schedule->owner_id]) && $classroomTypes[$schedule->owner_id] === 3) {
                            continue;
                        }
                        $classroomOccupancy[$rowIndex + 1][$item->day_index + 1][$schedule->owner_id] = true;
                    }
                }
            }
        }

        $result = [];
        foreach ($slots as $rowIndex => $slot) {
            $rowKey = $rowIndex + 1;
            for ($dayIndex = 0; $dayIndex <= $maxDayIndex; $dayIndex++) {
                $colKey = $dayIndex + 1;
                $hasAvailable = false;

                foreach ($classroomIds as $id) {
                    if (!isset($classroomOccupancy[$rowKey][$colKey][$id])) {
                        $hasAvailable = true;
                        break;
                    }
                }

                if (!$hasAvailable) {
                    if (!isset($result[$rowKey])) {
                        $result[$rowKey] = [];
                    }
                    $result[$rowKey][$colKey] = true;
                }
            }
        }

        return ["unavailableCells" => $result];
    }

    /**
     * Program bazlı çakışmaları kontrol eder.
     *
     * @param array $filters Validated filtreler:
     *   lesson_id, type, semester, academic_year, week_index
     * @return array [unavailableCells => ...]
     * @throws Exception
     */
    public function getProgramAvailability(array $filters): array
    {
        $lesson = (new Lesson())->where([
            'id' => $filters['lesson_id'],
        ])->with(['program', 'childLessons'])->first() ?: throw new Exception("Ders bulunamadı");
        $program = $lesson->program;

        $slots = $this->getTimeSlots($filters['type']);
        $unavailableCells = [];

        $schedules = (new Schedule())->get()->where([
            'owner_type' => 'program',
            'owner_id' => $program->id,
            'type' => $filters['type'],
            'semester' => $filters['semester'],
            'academic_year' => $filters['academic_year'],
            'semester_no' => $lesson->semester_no
        ])->all();
        // çocuk derslerin programları da dahil ediliyor
        if (!empty($lesson->childLessons)) {
            foreach ($lesson->childLessons as $childLesson) {
                if ($childLesson->program_id) {
                    $childSchedules = (new Schedule())->get()->where([
                        'owner_type' => 'program',
                        'owner_id' => $childLesson->program_id,
                        'type' => $filters['type'],
                        'semester' => $filters['semester'],
                        'academic_year' => $filters['academic_year'],
                        'semester_no' => $childLesson->semester_no
                    ])->all();
                    $schedules = array_merge($schedules, $childSchedules);
                }
            }
        }

        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where([
                'schedule_id' => $schedule->id,
                'week_index' => $filters['week_index']
            ])->all();

            foreach ($items as $item) {
                $itemStart = substr($item->start_time, 0, 5);
                $itemEnd = substr($item->end_time, 0, 5);

                $overlap = false;
                foreach ($slots as $rowIndex => $slot) {
                    if ($this->checkTimeOverlap($itemStart, $itemEnd, $slot['start'], $slot['end'])) {
                        // Eğer mevcut ders gruplu ise ve çakışan item da gruplu ise grup numaralarını kontrol et
                        if ($lesson->group_no > 0 && $item->status === 'group' && !empty($item->data)) {
                            $sameGroupExists = false;
                            foreach ($item->data as $slotData) {
                                // $slotData içerisinde ders bilgisi alınmalı. 
                                // ScheduleItem modelindeki getSlotDatas() mantığına benzer bir kontrol
                                if (isset($slotData['lesson_id'])) {
                                    $itemLesson = (new Lesson())->find($slotData['lesson_id']);
                                    if ($itemLesson && $itemLesson->group_no == $lesson->group_no) {
                                        $sameGroupExists = true;
                                        break;
                                    }
                                }
                            }
                            
                            if (!$sameGroupExists) {
                                continue; // Farklı gruplar çakışabilir, bu item'ı atla
                            }
                        }

                        $unavailableCells[$rowIndex + 1][$item->day_index + 1] = true;
                    }
                }
            }
        }

        return ["unavailableCells" => $unavailableCells];
    }

    /**
     * Ayarlara göre zaman dilimlerini (slots) oluşturur.
     */
    private function getTimeSlots(string $scheduleType): array
    {
        $type = in_array($scheduleType, ['midterm-exam', 'final-exam', 'makeup-exam']) ? 'exam' : 'lesson';
        $duration = (int) getSettingValue('duration', $type, $type === 'exam' ? 30 : 50);
        $break = (int) getSettingValue('break', $type, $type === 'exam' ? 0 : 10);

        $slots = [];
        $start = new DateTime('08:00');
        $end = new DateTime('17:00');
        while ($start < $end) {
            $slotStart = clone $start;
            $slotEnd = (clone $start)->modify("+$duration minutes");
            $slots[] = ['start' => $slotStart->format('H:i'), 'end' => $slotEnd->format('H:i')];
            $start = (clone $slotEnd)->modify("+$break minutes");
        }
        return $slots;
    }

    /**
     * İki zaman aralığının çakışıp çakışmadığını kontrol eder.
     * H:i:s formatını otomatik normalize eder.
     */
    private function checkTimeOverlap(
        string $start1,
        string $end1,
        string $start2,
        string $end2
    ): bool {
        $start1 = substr($start1, 0, 5);
        $end1 = substr($end1, 0, 5);
        $start2 = substr($start2, 0, 5);
        $end2 = substr($end2, 0, 5);

        return ($start1 < $end2) && ($start2 < $end1);
    }

    /**
     * Sınav atamalarında müsait gözetmenlerin listesini döndürür.
     *
     * Gözetmen havuzu: lecturer, department_head, manager, submanager
     * Belirtilen gün/hafta/zaman aralığında çakışan gözetmenler filtrelenir.
     *
     * @param array $filters Validated filtreler:
     *   type, semester, academic_year, day_index, week_index, items (JSON)
     * @return \App\Models\User[] Müsait gözetmenler
     * @throws Exception
     */
    public function availableObservers(array $filters = []): array
    {
        $observerFilters = [
            'role' => ['in' => ['lecturer', 'department_head', 'manager', 'submanager']]
        ];
        $observers = (new \App\Controllers\UserController())->getListByFilters($observerFilters);
        $itemsToCheck = json_decode($filters['items'] ?? '[]', true) ?: [];

        $availableObservers = [];

        foreach ($observers as $observer) {
            $userSchedule = (new Schedule())->firstOrCreate([
                'type' => $filters['type'],
                'owner_type' => 'user',
                'owner_id' => $observer->id,
                'semester_no' => null,
                'semester' => $filters['semester'],
                'academic_year' => $filters['academic_year'],
            ]);

            $existingItems = (new ScheduleItem())->get()->where([
                'schedule_id' => $userSchedule->id,
                'day_index' => $filters['day_index'],
                'week_index' => $filters['week_index'],
            ])->all();

            $isAvailable = true;
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

            if ($isAvailable) {
                $availableObservers[] = $observer;
            }
        }

        return $availableObservers;
    }
}
