<?php

namespace App\Services\Schedule;

use App\Services\BaseService;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Enums\ScheduleItemStatus;
use App\Repositories\UserRepository;
use App\Repositories\LessonRepository;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\User;
use App\Models\LessonAssignment;
use App\DTOs\AvailabilityFilterDTO;
use Exception;
use App\Helpers\TimeHelper;
use function App\Helpers\getSettingValue;
use App\Enums\LessonType;
use App\Repositories\ScheduleRepository;
use App\Repositories\LessonAssignmentRepository;

/**
 * Ders ve Sınav programlarında müsait derslik ve gözetmen sorgulama servisi.
 *
 * availableClassrooms: Ders ve sınav için ortak kullanılır;
 *   iç mantık schedule.type'a göre ders/sınav filtresi uygular.
 */
class AvailabilityService extends BaseService
{
    protected string $logChannel = 'schedule';
    private TimelineManager $timelineManager;
    private ScheduleRepository $scheduleRepo;

    public function __construct()
    {
        parent::__construct();
        $this->timelineManager = new TimelineManager();
        $this->scheduleRepo = new ScheduleRepository();
    }

    /**
     * Belirtilen filtrelere uygun dersliklerin listesini döndürür.
     *
     * Ders programı → dersin classroom_type değerine uygun sınıflar.
     * Sınav programı → UZEM (type=3) hariç tüm sınıflar.
     *
     * @param AvailabilityFilterDTO|array $filters Validated filtreler
     * @return Classroom[] Müsait derslik nesneleri
     * @throws Exception
     */
    public function availableClassrooms(AvailabilityFilterDTO|array $filters = []): array
    {
        $dto = $filters instanceof AvailabilityFilterDTO ? $filters : AvailabilityFilterDTO::fromArray($filters);

        if (!$dto->schedule_id) {
            throw new Exception("Uygun derslikleri belirlemek için Program ID belirtilmelidir");
        }

        $schedule = (new Schedule())
            ->where(["id" => $dto->schedule_id])
            ->with("items")
            ->first()
            ?: throw new Exception("Uygun derslikleri belirlemek için Program bulunamadı");

        if (!$dto->lesson_id) {
            throw new Exception("Derslik türünü belirlemek için ders ID belirtilmelidir");
        }

        $lesson = (new Lesson())->find($dto->lesson_id)
            ?: throw new Exception("Derslik türünü belirlemek için ders bulunamadı");

        if ((int)$lesson->type === LessonType::INTERNSHIP->value) {
            return [];
        }

        $whereConditions = [];
        if (!empty($lesson->building_id)) {
            $whereConditions['building_id'] = $lesson->building_id;
        }

        if (ExamType::isExamType($schedule->type)) {
            // Sınav → UZEM (type=3) hariç tüm derslikler
            $whereConditions['type'] = ['!=' => 3];
            $classrooms = (new Classroom())->get()->where($whereConditions)->all();
        } else {
            // Ders → classroom_type ile eşleşen derslikler (Karma=4 ise Lab+Derslik)
            $classroom_type = $lesson->classroom_type == 4 ? [1, 2] : [$lesson->classroom_type];
            $whereConditions['type'] = ['in' => $classroom_type];
            $classrooms = (new Classroom())->get()->where($whereConditions)->all();
        }

        $itemsToCheck = is_string($dto->items) ? (json_decode($dto->items, true) ?: []) : (is_array($dto->items) ? $dto->items : []);
        $availableClassrooms = [];

        foreach ($classrooms as $classroom) {
            $classroomSchedule = $this->scheduleRepo->findByOwnerAndPeriod(
                OwnerType::CLASSROOM->value,
                $classroom->id,
                $schedule->academic_year,
                $schedule->semester,
                $schedule->type,
                null
            );

            $existingItems = $classroomSchedule ? (new ScheduleItem())->get()->where([
                'schedule_id' => $classroomSchedule->id,
                'day_index'   => $dto->day_index,
                'week_index'  => $dto->week_index ?? 0,
            ])->all() : [];

            $isAvailable = true;

            // UZEM sınıfları her zaman uygun sayılır
            if ($classroom->type != 3) {
                foreach ($itemsToCheck as $checkItem) {
                    foreach ($existingItems as $existingItem) {
                        if (
                            TimeHelper::isOverlapping(
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
                    'id'          => 'dummy-preferred',
                    'name'        => '',
                    'code'        => 'PREF',
                    'status'      => ScheduleItemStatus::PREFERRED->value,
                    'hours'       => 1,
                    'lecturer_id' => $schedule->owner_id, // Context hoca ise hoca ID'si
                    'is_dummy'    => true
                ],
                (object) [
                    'id'          => 'dummy-unavailable',
                    'name'        => '',
                    'code'        => 'UNAV',
                    'status'      => ScheduleItemStatus::UNAVAILABLE->value,
                    'hours'       => 1,
                    'lecturer_id' => $schedule->owner_id,
                    'is_dummy'    => true
                ]
            ];
        }

        $available_lessons = [];

        // Aktif dönem ve akademik yılda ataması bulunan derslerin ID'leri
        $periodAssignments = (new LessonAssignment())->get()->where([
            'semester'      => $schedule->semester,
            'academic_year' => $schedule->academic_year
        ])->all();
        $periodLessonIds = array_unique(array_filter(array_column($periodAssignments, 'lesson_id')));

        $lessonFilters = [
            'id'    => ['in' => !empty($periodLessonIds) ? $periodLessonIds : [-1]],
            '!type' => 4 // staj dersleri dahil değil
        ];

        if ($schedule->owner_type == OwnerType::PROGRAM->value) {
            $lessonFilters = array_merge($lessonFilters, [
                'program_id' => $schedule->owner_id,
            ]);
        } elseif ($schedule->owner_type == OwnerType::CLASSROOM->value) {
            $classroom = (new Classroom())->find($schedule->owner_id);
            $lessonFilters = array_merge($lessonFilters, [
                'classroom_type' => $classroom->type,
            ]);
        } elseif ($schedule->owner_type == OwnerType::USER->value) {
            $asgns = (new LessonAssignmentRepository())->findByLecturer($schedule->owner_id, $schedule->semester, $schedule->academic_year);
            $assignedLessonIds = array_unique(array_filter(array_map(fn($a) => $a->lesson_id, $asgns)));
            $lessonFilters = array_merge($lessonFilters, [
                'id' => ['in' => !empty($assignedLessonIds) ? $assignedLessonIds : [-1]],
            ]);
            if ($schedule->type === 'lesson') {
                unset($lessonFilters["!type"]); // staj derslerini dahil et
            }
        } elseif ($schedule->owner_type == OwnerType::LESSON->value) {
            $lessonFilters = array_merge($lessonFilters, [
                'id' => $schedule->owner_id,
            ]);
        }

        // Yalnızca program schedule'larında semester_no filtresini uygula
        if ($schedule->owner_type === OwnerType::PROGRAM->value && $schedule->semester_no !== null) {
            $lessonFilters['semester_no'] = $schedule->semester_no;
        }
        $relationOptions = [
            'with'          => ['program'],
            'semester'      => $schedule->semester,
            'academic_year' => $schedule->academic_year
        ];
        $lessonsList = (new LessonRepository())->getAuthorized('update', $lessonFilters, [
            'lecturer'         => $relationOptions, 
            'program', 
            'parentLesson'     => $relationOptions, 
            'childLessons'     => $relationOptions, 
            'examParentLesson' => $relationOptions, 
            'examChildLessons' => $relationOptions
        ]);
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
                } elseif (ExamType::isExamType($schedule->type)) {
                    $lesson->size = $lesson->remaining_size; // kalan mevcut dersin mevcudu olarak güncelleniyor
                }

                $available_lessons[] = $lesson;
            }
        }
        // uygun dersler belirlendikten sonra sınav programında gruplu dersleri birleştirmek için yapılan işlem
        if (ExamType::isExamType($schedule->type)) {
            $available_lessons = array_values($available_lessons);
            $available_lessons = $this->groupExamLessons($available_lessons, $schedule->type);
        }

        return $available_lessons;
    }

    /**
     * Sınav programı için gruplu dersleri (aynı kod, farklı grup) tek bir ders olarak birleştirir.
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
            $groupLetters = [];

            foreach ($groupLessons as $l) {
                $groupLetters[] = chr(64 + $l->group_no);
            }

            sort($groupLetters);
            $representative->name .= " (Grup " . implode(", ", $groupLetters) . ")";

            $result[] = $representative;
        }

        return $result;
    }

    /**
     * Sınav atamalarında müsait gözetmenlerin listesini döndürür.
     *
     * Gözetmen havuzu: lecturer, department_head, manager, submanager
     * Belirtilen gün/hafta/zaman aralığında çakışan gözetmenler filtrelenir.
     *
     * @param AvailabilityFilterDTO|array $filters Validated filtreler
     * @return User[] Müsait gözetmenler
     * @throws Exception
     */
    public function availableObservers(AvailabilityFilterDTO|array $filters = []): array
    {
        $dto = $filters instanceof AvailabilityFilterDTO ? $filters : AvailabilityFilterDTO::fromArray($filters);

        $observerRoles = ['lecturer', 'department_head', 'manager', 'submanager'];
        $unitIds = $this->resolveScheduleUnitIds($dto->schedule_id);
        if (empty($unitIds)) {
            $observers = (new UserRepository())->findBy(['role' => ['in' => $observerRoles]]);
        } else {
            // Programın/hocanın birimindeki ve o birimde ders veren (affiliation) hocalar
            $observers = [];
            foreach ($unitIds as $unitId) {
                foreach ((new UserRepository())->getLecturersByUnit($unitId) as $candidate) {
                    if (in_array($candidate->role, $observerRoles, true)) {
                        $observers[$candidate->id] = $candidate;
                    }
                }
            }
            $observers = array_values($observers);
        }
        $itemsToCheck = is_string($dto->items) ? (json_decode($dto->items, true) ?: []) : (is_array($dto->items) ? $dto->items : []);

        $availableObservers = [];

        foreach ($observers as $observer) {
            $userSchedule = $this->scheduleRepo->findByOwnerAndPeriod(
                OwnerType::USER->value,
                $observer->id,
                $dto->academic_year,
                $dto->semester,
                $dto->type,
                null
            );

            $existingItems = $userSchedule ? (new ScheduleItem())->get()->where([
                'schedule_id' => $userSchedule->id,
                'day_index'   => $dto->day_index,
                'week_index'  => $dto->week_index ?? 0,
            ])->all() : [];

            $isAvailable = true;
            foreach ($itemsToCheck as $checkItem) {
                foreach ($existingItems as $existingItem) {
                    if (
                        TimeHelper::isOverlapping(
                            $checkItem['start_time'],
                            $checkItem['end_time'],
                            $existingItem->start_time,
                            $existingItem->end_time
                        )
                    ) {
                        if ($existingItem->status == ScheduleItemStatus::PREFERRED->value) {
                            $observer->title = "**" . $observer->title;
                            continue;
                        }
                        $isAvailable = false;
                        break 2;
                    }
                }
            }

            if ($isAvailable) {
                $observer->day_note = $this->observerDayNote($observer, $dto, $existingItems);
                $availableObservers[] = $observer;
            }
        }

        // Hem dersi hem sınavı olanlar en üstte, ardından biri olanlar, sonra diğerleri (usort kararlıdır)
        $rank = fn(User $o) => $o->day_note === null ? 0 : (str_contains($o->day_note, ' ve ') ? 2 : 1);
        usort($availableObservers, fn(User $a, User $b) => $rank($b) <=> $rank($a));

        return $availableObservers;
    }

    /**
     * Programın sahibinin bağlı olduğu birim ID'lerini döndürür.
     * Program için bölümün birimi, hoca için kadro ve affiliation birimleri, ders için dersin bölümünün birimi.
     * Çözümlenemezse boş dizi döner (filtre uygulanmaz).
     *
     * @param int|null $scheduleId
     * @return int[]
     * @throws Exception
     */
    private function resolveScheduleUnitIds(?int $scheduleId): array
    {
        if (!$scheduleId) {
            return [];
        }
        $schedule = $this->scheduleRepo->find($scheduleId);
        if (!$schedule) {
            return [];
        }

        $unitIds = match ($schedule->owner_type) {
            OwnerType::PROGRAM->value => [(new Program())->where(['id' => $schedule->owner_id])->with(['department'])->first()?->department?->unit_id],
            OwnerType::USER->value => (new User())->find($schedule->owner_id)?->getRelatedUnitIds() ?? [],
            OwnerType::LESSON->value => [(new Lesson())->where(['id' => $schedule->owner_id])->with(['department'])->first()?->department?->unit_id],
            default => [],
        };

        return array_values(array_unique(array_map('intval', array_filter($unitIds))));
    }

    /**
     * Gözetmenin sınavın yapılacağı gündeki ders ve sınavlarını kısa metin olarak döndürür.
     *
     * @param User $observer
     * @param AvailabilityFilterDTO $dto
     * @param ScheduleItem[] $examDayItems Gözetmenin aynı tipteki programında o güne ait kayıtları
     * @return string|null Örn. "Çarşamba günü dersi ve sınavı var"; yoksa null
     * @throws Exception
     */
    private function observerDayNote(User $observer, AvailabilityFilterDTO $dto, array $examDayItems): ?string
    {
        $lessonSchedule = $this->scheduleRepo->findByOwnerAndPeriod(
            OwnerType::USER->value,
            $observer->id,
            $dto->academic_year,
            $dto->semester,
            'lesson',
            null
        );
        $lessonDayItems = $lessonSchedule ? (new ScheduleItem())->get()->where([
            'schedule_id' => $lessonSchedule->id,
            'day_index'   => $dto->day_index,
            'week_index'  => 0,
        ])->all() : [];

        // Tercih/müsait değil gibi ders içermeyen kayıtlar sayılmaz
        $hasData = fn(array $items) => count(array_filter($items, fn($i) => !empty($i->data))) > 0;
        $has = array_filter([
            'dersi' => $hasData($lessonDayItems),
            'sınavı' => $hasData($examDayItems),
        ]);
        if (!$has) {
            return null;
        }

        $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
        $dayName = $days[$dto->day_index] ?? "Bu";

        return "$dayName günü " . implode(' ve ', array_keys($has)) . " var";
    }

    /**
     * Hocanın tercih ettiği ve engellediği saat bilgilerini döner
     *
     * @param AvailabilityFilterDTO|array $filters Validated filtreler
     * @return array [unavailableCells => ..., preferredCells => ...]
     * @throws Exception
     */
    public function getLecturerAvailability(AvailabilityFilterDTO|array $filters): array
    {
        $dto = $filters instanceof AvailabilityFilterDTO ? $filters : AvailabilityFilterDTO::fromArray($filters);

        if (!$dto->lesson_id) {
            throw new Exception("Hoca müsaitliğini kontrol etmek için ders ID gereklidir");
        }

        $lesson = (new Lesson())->where(['id' => $dto->lesson_id])->with(['lecturer'])->first()
            ?: throw new Exception("Ders bulunamadı");
        $lecturer = $lesson->lecturer;

        $slots = $this->timelineManager->getTimeSlots($dto->type);
        $unavailableCells = [];
        $preferredCells = [];

        $schedules = (new Schedule())->get()->where([
            'owner_type'    => OwnerType::USER->value,
            'owner_id'      => $lecturer->id,
            'type'          => $dto->type,
            'semester'      => $dto->semester,
            'academic_year' => $dto->academic_year,
        ])->with(['items'])->all();

        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where([
                'schedule_id' => $schedule->id,
                'week_index'  => $dto->week_index ?? 0
            ])->all();

            foreach ($items as $item) {
                foreach ($slots as $rowIndex => $slot) {
                    if (TimeHelper::isOverlapping($item->start_time, $item->end_time, $slot['start'], $slot['end'])) {
                        if ($item->status === ScheduleItemStatus::PREFERRED->value) {
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
            "preferredCells"   => $preferredCells
        ];
    }

    /**
     * Dersliklerin doluluk durumuna göre müsait olmayan hücreleri döner.
     *
     * @param AvailabilityFilterDTO|array $filters Validated filtreler
     * @return array [unavailableCells => ...]
     * @throws Exception
     */
    public function getClassroomAvailability(AvailabilityFilterDTO|array $filters): array
    {
        $dto = $filters instanceof AvailabilityFilterDTO ? $filters : AvailabilityFilterDTO::fromArray($filters);

        if (!$dto->lesson_id) {
            throw new Exception("Derslik müsaitliğini kontrol etmek için ders ID gereklidir");
        }

        $lesson = (new Lesson())->find($dto->lesson_id) ?: throw new Exception("Ders bulunamadı");
        if ((int)$lesson->type === LessonType::INTERNSHIP->value) {
            return ["unavailableCells" => []];
        }

        $classroom_type = $lesson->classroom_type == 4 ? [1, 2] : [$lesson->classroom_type];
        $classrooms = (new Classroom())->get()->where(['type' => ['in' => $classroom_type]])->all();

        $slots = $this->timelineManager->getTimeSlots($dto->type);
        $type = ExamType::isExamType($dto->type) ? 'exam' : 'lesson';
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);

        $classroomOccupancy = [];
        $classroomIds = array_column($classrooms, 'id');
        $classroomTypes = [];
        foreach ($classrooms as $c) {
            $classroomTypes[$c->id] = (int) $c->type;
        }

        $schedules = (new Schedule())->get()->where([
            'owner_type'    => OwnerType::CLASSROOM->value,
            'owner_id'      => ['in' => $classroomIds],
            'type'          => $dto->type,
            'semester'      => $dto->semester,
            'academic_year' => $dto->academic_year,
        ])->all();

        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where([
                'schedule_id' => $schedule->id,
                'week_index'  => $dto->week_index ?? 0
            ])->all();

            foreach ($items as $item) {
                foreach ($slots as $rowIndex => $slot) {
                    if (TimeHelper::isOverlapping($item->start_time, $item->end_time, $slot['start'], $slot['end'])) {
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
     * @param AvailabilityFilterDTO|array $filters Validated filtreler
     * @return array [unavailableCells => ...]
     * @throws Exception
     */
    public function getProgramAvailability(AvailabilityFilterDTO|array $filters): array
    {
        $dto = $filters instanceof AvailabilityFilterDTO ? $filters : AvailabilityFilterDTO::fromArray($filters);

        if (!$dto->lesson_id) {
            throw new Exception("Program müsaitliğini kontrol etmek için ders ID gereklidir");
        }

        $lesson = (new Lesson())->where([
            'id' => $dto->lesson_id,
        ])->with(['program', 'childLessons'])->first() ?: throw new Exception("Ders bulunamadı");
        $program = $lesson->program ?: ($lesson->program_id ? (new Program())->find($lesson->program_id) : null);

        $slots = $this->timelineManager->getTimeSlots($dto->type);
        $unavailableCells = [];

        $schedules = [];

        $ownerType = $dto->owner_type;

        if ($ownerType !== OwnerType::PROGRAM->value && $program) {
            $schedules = (new Schedule())->get()->where([
                'owner_type'    => OwnerType::PROGRAM->value,
                'owner_id'      => $program->id,
                'type'          => $dto->type,
                'semester'      => $dto->semester,
                'academic_year' => $dto->academic_year,
                'semester_no'   => $lesson->semester_no
            ])->all();
        }

        // çocuk derslerin programları da dahil ediliyor
        if (!empty($lesson->childLessons)) {
            foreach ($lesson->childLessons as $childLesson) {
                if ($childLesson->program_id) {
                    if ($ownerType === OwnerType::PROGRAM->value && $program && $childLesson->program_id == $program->id) {
                        continue;
                    }
                    $childSchedules = (new Schedule())->get()->where([
                        'owner_type'    => OwnerType::PROGRAM->value,
                        'owner_id'      => $childLesson->program_id,
                        'type'          => $dto->type,
                        'semester'      => $dto->semester,
                        'academic_year' => $dto->academic_year,
                        'semester_no'   => $childLesson->semester_no
                    ])->all();
                    $schedules = array_merge($schedules, $childSchedules);
                }
            }
        }

        $lessonCache = [];
        $getLesson = function (int|string $id) use (&$lessonCache): ?Lesson {
            $key = (int) $id;
            if (!array_key_exists($key, $lessonCache)) {
                $lessonCache[$key] = (new Lesson())->find($key);
            }
            return $lessonCache[$key];
        };

        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where([
                'schedule_id' => $schedule->id,
                'week_index'  => $dto->week_index ?? 0
            ])->all();

            foreach ($items as $item) {
                $isDraggedInternship = ((int)$lesson->type === LessonType::INTERNSHIP->value);

                // İtem içindeki dersleri topla
                $itemLessons = [];
                if (!empty($item->data)) {
                    foreach ($item->data as $slotData) {
                        if (isset($slotData['lesson_id'])) {
                            $l = $getLesson($slotData['lesson_id']);
                            if ($l) {
                                $itemLessons[] = $l;
                            }
                        }
                    }
                }

                $hasNormalLesson = false;
                $hasInternshipLesson = false;
                $sameInternshipGroupExists = false;

                foreach ($itemLessons as $iLesson) {
                    if ((int)$iLesson->type === LessonType::INTERNSHIP->value) {
                        $hasInternshipLesson = true;
                        if ($iLesson->group_no == $lesson->group_no) {
                            $sameInternshipGroupExists = true;
                        }
                    } else {
                        $hasNormalLesson = true;
                    }
                }

                // 1) Sürüklenen ders staj dersi ise:
                if ($isDraggedInternship) {
                    // Normal dersler staj dersini engellemez (çakışma sayılmaz)
                    // Sadece aynı staj grubu varsa çakışma sayılır
                    if (!$sameInternshipGroupExists) {
                        continue;
                    }
                } else {
                    // 2) Sürüklenen ders normal ders ise:
                    // Programdaki staj dersleri normal dersi engellemez
                    if (!$hasNormalLesson) {
                        continue;
                    }
                }

                // Eğer mevcut ders gruplu ise ve çakışan item da gruplu ise grup numaralarını kontrol et
                if (!$isDraggedInternship && $lesson->group_no > 0 && $item->status === ScheduleItemStatus::GROUP->value) {
                    $sameGroupExists = false;
                    foreach ($itemLessons as $iLesson) {
                        if ($iLesson->group_no == $lesson->group_no) {
                            $sameGroupExists = true;
                            break;
                        }
                    }
                    
                    if (!$sameGroupExists) {
                        continue;
                    }
                }

                foreach ($slots as $rowIndex => $slot) {
                    if (TimeHelper::isOverlapping($item->start_time, $item->end_time, $slot['start'], $slot['end'])) {
                        $unavailableCells[$rowIndex + 1][$item->day_index + 1] = true;
                    }
                }
            }
        }

        return ["unavailableCells" => $unavailableCells];
    }
}
