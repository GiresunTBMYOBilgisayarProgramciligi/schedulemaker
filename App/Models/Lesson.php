<?php

namespace App\Models;

use App\Controllers\ClassroomController;
use App\Controllers\LessonController;
use App\Core\Model;
use Exception;
use function App\Helpers\getSettingValue;
use function App\Helpers\formatLessonName;

class Lesson extends Model
{
    public ?int $id = null;
    public ?string $code = null;
    public ?int $group_no = null;
    public ?string $name = null;
    public ?int $size = null;
    public ?int $hours = null;
    /**
     * @var int|null
     * @see LessonController->getTypeList()
     */
    public ?int $type = null;
    public ?int $semester_no = null;
    public ?int $lecturer_id = null;
    public ?int $department_id = null;
    public ?int $program_id = null;
    /**
     * Güz, Bahar, Yaz
     * @var string|null
     */
    public ?string $semester = null;
    /**
     *
     * @var int|null
     * @see ClassroomController->getTypeList()
     */
    public ?int $classroom_type = null;
    public ?string $academic_year = null;
    public ?int $parent_lesson_id = null;
    /**
     * Ders programına eklemeye uygun olmayan saat miktarı. Bu saatler zaten programa eklenmiş
     * @var int|null
     */
    public ?int $placed_hours = 0;
    /**
     * Sınav programına eklemeye uygun olmayan mevcut. Bu mevcut zaten programa eklenmiş
     * @var int|null
     */
    public ?int $placed_size = 0;
    public ?int $remaining_size = 0;

    public ?User $lecturer = null;
    public ?Department $department = null;
    public ?Program $program = null;
    public ?Lesson $parentLesson = null;
    public array $childLessons = [];
    public array $schedules = [];
    protected string $table_name = "lessons";
    protected array $excludeFromDb = ['lecturer', 'department', 'program', 'parentLesson', 'childLessons', 'schedules', 'placed_hours', 'placed_size', 'remaining_size'];

    /**
     * @throws Exception
     */
    protected function beforeDelete(): void
    {
        // Not: İlişkili programlar (schedules) ve polimorfik kardeş kayıtlar (sibling items) temizlenir.
        (new \App\Controllers\ScheduleController())->wipeResourceSchedules('lesson', $this->id);
    }

    public function getLabel(): string
    {
        return "ders";
    }

    /**
     * @param array $data
     * @return void
     * @throws Exception
     */
    public function fill(array $data = []): void
    {
        if (isset($data['name'])) {
            $data['name'] = formatLessonName($data['name']);
        }
        parent::fill($data);
    }

    public function getLogDetail(): string
    {
        return $this->getFullName();
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getSchedulesRelation(array $results, array $options = []): array
    {
        $ids = array_column($results, 'id');
        if (empty($ids))
            return $results;

        $query = (new Schedule())->get()
            ->where([
                'owner_type' => 'lesson',
                'owner_id' => ['in' => $ids]
            ]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $schedules = $query->all();

        $schedulesGrouped = [];
        foreach ($schedules as $schedule) {
            $schedulesGrouped[$schedule->owner_id][] = $schedule;
        }

        foreach ($results as &$row) {
            $row['schedules'] = $schedulesGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getLecturerRelation(array $results, array $options = []): array
    {
        $userIds = array_unique(array_column($results, 'lecturer_id'));
        if (empty($userIds))
            return $results;

        $query = (new User())->get()->where(['id' => ['in' => $userIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
        $usersKeyed = [];
        foreach ($users as $user) {
            $usersKeyed[$user->id] = $user;
        }

        foreach ($results as &$row) {
            if (isset($row['lecturer_id']) && isset($usersKeyed[$row['lecturer_id']])) {
                $row['lecturer'] = $usersKeyed[$row['lecturer_id']];
            } else {
                $row['lecturer'] = null;
            }
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getDepartmentRelation(array $results, array $options = []): array
    {
        $deptIds = array_unique(array_column($results, 'department_id'));
        if (empty($deptIds))
            return $results;

        $query = (new Department())->get()->where(['id' => ['in' => $deptIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $departments = $query->all();
        $departmentsKeyed = [];
        foreach ($departments as $dept) {
            $departmentsKeyed[$dept->id] = $dept;
        }

        foreach ($results as &$row) {
            if (isset($row['department_id']) && isset($departmentsKeyed[$row['department_id']])) {
                $row['department'] = $departmentsKeyed[$row['department_id']];
            } else {
                $row['department'] = null;
            }
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getProgramRelation(array $results, array $options = []): array
    {
        $progIds = array_unique(array_column($results, 'program_id'));
        if (empty($progIds))
            return $results;

        $query = (new Program())->get()->where(['id' => ['in' => $progIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $programs = $query->all();
        $programsKeyed = [];
        foreach ($programs as $prog) {
            $programsKeyed[$prog->id] = $prog;
        }

        foreach ($results as &$row) {
            if (isset($row['program_id']) && isset($programsKeyed[$row['program_id']])) {
                $row['program'] = $programsKeyed[$row['program_id']];
            } else {
                $row['program'] = null;
            }
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getParentLessonRelation(array $results, array $options = []): array
    {
        $parentIds = array_unique(array_column($results, 'parent_lesson_id'));
        // remove nulls
        $parentIds = array_filter($parentIds);
        if (empty($parentIds))
            return $results;

        $query = (new Lesson())->get()->where(['id' => ['in' => $parentIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $lessons = $query->all();
        $lessonsKeyed = [];
        foreach ($lessons as $lesson) {
            $lessonsKeyed[$lesson->id] = $lesson;
        }

        foreach ($results as &$row) {
            if (isset($row['parent_lesson_id']) && isset($lessonsKeyed[$row['parent_lesson_id']])) {
                $row['parentLesson'] = $lessonsKeyed[$row['parent_lesson_id']];
            } else {
                $row['parentLesson'] = null;
            }
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getChildLessonsRelation(array $results, array $options = []): array
    {
        $ids = array_column($results, 'id');
        if (empty($ids))
            return $results;

        $query = (new Lesson())->get()->where(['parent_lesson_id' => ['in' => $ids]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $lessons = $query->all();
        $lessonsGrouped = [];
        foreach ($lessons as $lesson) {
            $lessonsGrouped[$lesson->parent_lesson_id][] = $lesson;
        }

        foreach ($results as &$row) {
            $row['childLessons'] = $lessonsGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @return string
     */
    public function getFullName(): string
    {
        return trim($this->name . " (" . $this->code . ")");
    }

    /**
     * Bağlı derslerin (veli ve tüm çocuklar) ID listesini döner.
     * @return array
     */
    public function getLinkedLessonIds(): array
    {
        $rootId = $this->parent_lesson_id ?: $this->id;
        $ids = [$rootId];
        $children = (new Lesson())->get()->where(['parent_lesson_id' => $rootId])->all();
        foreach ($children as $child) {
            $ids[] = $child->id;
        }
        return array_unique($ids);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getClassroomTypeName(): string
    {
        return (new ClassroomController())->getTypeList()[$this->classroom_type] ?? "";
    }

    public function getTypeName(): string
    {
        return (new LessonController())->getTypeList()[$this->type] ?? "";
    }

    /**
     * Ders saati ile ders adına kayıtlı schedule sayısı aynı ise ders ders programı tamamlanmıştır.
     * @param string $type schedule type
     * @return bool true if complete
     * @throws Exception
     */
    public function IsScheduleComplete(string $type = "lesson"): bool
    {
        $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
        $isExam = in_array($type, $examTypes);

        if ($isExam) {
            $linkedIds = $this->getLinkedLessonIds();
            // Toplam grup mevcudunu hesapla
            $targetSize = (new Lesson())->get()->where(['id' => ['in' => $linkedIds]])->sum('size');

            // Tüm bağlı derslerin programlarını çek
            $schedules = (new Schedule())->get()->where([
                'owner_type' => 'lesson',
                'owner_id' => ['in' => $linkedIds],
                'type' => $type,
                'semester' => $this->semester,
                'academic_year' => $this->academic_year
            ])->with('items')->all();
        } else {
            $targetSize = isset($this->hours) ? $this->hours : 0;
            $schedules = (new Schedule())->get()->where([
                'owner_type' => 'lesson',
                'owner_id' => $this->id,
                'type' => $type,
                'semester' => $this->semester,
                'academic_year' => $this->academic_year
            ])->with('items')->all();
        }

        $items = [];
        foreach ($schedules as $schedule) {
            foreach ($schedule->items as $item) {
                $items[] = $item;
            }
        }

        $this->placed_size = 0;
        if ($targetSize <= 0) {
            $this->remaining_size = 0;
            $this->placed_hours = 0;
            return true;
        }

        if (empty($items)) {
            $this->remaining_size = $targetSize;
            $this->placed_hours = 0;
            return false;
        }

        if ($isExam) {
            // Aynı slotta (hafta, gün, saat) birden fazla derslikte sınav olabilir.
            // Kullanıcı talebi: Derslik programlarına bakılmalı.
            $uniqueClassroomSlots = []; // [ 'week-day-start-classId' => assignment_data ]

            foreach ($items as $item) {
                $detail = is_string($item->detail) ? json_decode($item->detail, true) : $item->detail;
                if (isset($detail['assignments']) && is_array($detail['assignments'])) {
                    foreach ($detail['assignments'] as $assignment) {
                        $classId = $assignment['classroom_id'] ?? null;
                        if ($classId) {
                            $key = "{$item->week_index}_{$item->day_index}_{$item->start_time}_{$classId}";
                            $uniqueClassroomSlots[$key] = [
                                'classroom_id' => $classId,
                                'week' => $item->week_index,
                                'day' => $item->day_index,
                                'start' => $item->start_time
                            ];
                        }
                    }
                }
            }

            foreach ($uniqueClassroomSlots as $slot) {
                // Her derslik için o zamandaki resmi program kaydını (owner_type=classroom) bul
                $classroomSchedule = (new Schedule())->get()->where([
                    'owner_type' => 'classroom',
                    'owner_id' => $slot['classroom_id'],
                    'type' => $type,
                    'semester' => $this->semester,
                    'academic_year' => $this->academic_year
                ])->first();

                if ($classroomSchedule) {
                    $classroomItem = (new ScheduleItem())->get()->where([
                        'schedule_id' => $classroomSchedule->id,
                        'week_index' => $slot['week'],
                        'day_index' => $slot['day'],
                        'start_time' => $slot['start']
                    ])->first();

                    if ($classroomItem) {
                        $cDetail = is_string($classroomItem->detail) ? json_decode($classroomItem->detail, true) : $classroomItem->detail;
                        if (isset($cDetail['assignments']) && is_array($cDetail['assignments'])) {
                            foreach ($cDetail['assignments'] as $asgn) {
                                if ($asgn['classroom_id'] == $slot['classroom_id']) {
                                    $this->placed_size += (int) ($asgn['classroom_exam_size'] ?? 0);
                                    break;
                                }
                            }
                        } else {
                            // Geriye dönük uyumluluk: getSlotDatas üzerinden
                            foreach ($classroomItem->getSlotDatas() as $gd) {
                                if (isset($gd->classroom) && $gd->classroom->id == $slot['classroom_id']) {
                                    $this->placed_size += (int) ($gd->classroom->exam_size ?? 0);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            $this->logger()->debug("IsScheduleComplete(exam-classroom-verified) lesson {$this->id}: target={$targetSize}, placed={$this->placed_size}");
        } else {
            $lessonDuration = getSettingValue('duration', 'lesson', 50);
            $breakDuration = getSettingValue('break', 'lesson', 10);
            $totalSlotDuration = $lessonDuration + $breakDuration;

            foreach ($items as $item) {
                if ($item->status === 'unavailable' || $item->status === 'preferred') {
                    continue;
                }
                $start = \DateTime::createFromFormat('H:i:s', $item->start_time) ?: \DateTime::createFromFormat('H:i', $item->start_time);
                $end = \DateTime::createFromFormat('H:i:s', $item->end_time) ?: \DateTime::createFromFormat('H:i', $item->end_time);

                if ($start && $end) {
                    $diffMinutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                    $this->placed_size += round($diffMinutes / $totalSlotDuration);
                }
            }
            $this->placed_hours = $this->placed_size;
        }

        $this->remaining_size = $targetSize - $this->placed_size;

        return $this->remaining_size <= 0;
    }

    public function getScheduleCSSClass(): string
    {
        $isChild = !is_null($this->parent_lesson_id);

        // 1. Ders Türü Belirleme
        $typeClass = "lesson-type-normal"; // Varsayılan
        if ($isChild) {
            $typeClass = "lesson-type-child";
        } elseif ($this->classroom_type == 2) {
            $typeClass = "lesson-type-lab";
        } elseif ($this->classroom_type == 3) {
            $typeClass = "lesson-type-uzem";
        }

        // 2. Grup Belirleme (Opsiyonel Ek Sınıf)
        $groupClass = "";
        if ($this->group_no > 0) {
            $groupClass = "lesson-group-" . $this->group_no;
        }

        // Nihai Sınıf Listesi
        $finalClass = trim("$typeClass $groupClass");

        return $finalClass;
    }
}