<?php

namespace App\Models;

use App\Controllers\ClassroomController;
use App\Controllers\LessonController;
use App\Core\Model;
use Exception;
use function App\Helpers\getSettingValue;

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
    protected array $excludeFromDb = ['lecturer', 'department', 'program', 'parentLesson', 'childLessons', 'schedules', 'placed_hours', 'placed_size', 'remaining_size'];
    protected string $table_name = "lessons";

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
        /**
         * derse ait schedule bulunur
         * bu schedule a ait schedule itemler bulunur.
         * schedule itemi yoksa $this->remaining_size = $this->size; yapılır ve false dönülür.
         * item varsa status bilgisi unavailable ve preferred olmayanların ders saat sayısı ayarlardan gelen ders saat süresine göre hesaplanır.
         * itemde hesaplanan saat sayısı $this->placed_size olarak kaydedilir.
         * $this->remaining_size = $this->size - $this->placed_size; ile kalan saat sayısı hesaplanır.
         * kalan saat 0 ise true döndürülür.
         *
         * sınav programları için itemlerin getSlotDatas metodu kullanılarak dersliklerin sınav mevcudu toplanarak ddersin mevcudunu karşılayıp karşılamadığına bakılır.
         */
        $schedules = (new Schedule())->get()->where([
            'owner_type' => 'lesson',
            'owner_id' => $this->id,
            'type' => $type
        ])->with('items')->all();

        $items = [];
        foreach ($schedules as $schedule) {
            foreach ($schedule->items as $item) {
                $items[] = $item;
            }
        }

        $targetSize = ($type == 'lesson' && isset($this->hours)) ? $this->hours : $this->size;
        $this->placed_size = 0;
        /**
         * mevcut ve/ya saat 0 ise program tamamlanmış sayılır
         */
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

        $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
        if (in_array($type, $examTypes)) {
            foreach ($items as $item) {
                foreach ($item->getSlotDatas() as $data) {
                    if (isset($data->classroom)) {
                        $this->placed_size += $data->classroom->exam_size;
                    }
                }
            }
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
                    // Eğer süre tam bölünmüyorsa yukarı yuvarla (örn: 50dk ders + 10dk teneffüs = 60dk)
                    // Ancak tek ders 50dk olabilir, bu yüzden diffMinutes 50 ise 1 sayılmalı.
                    // Bu mantıkla: diffMinutes / totalSlotDuration.
                    // Örnek: 230 dk blok. 230 / 60 = 3.83 => 4 saat.
                    // Örnek: 50 dk blok. 50 / 60 = 0.83 => 1 saat.
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