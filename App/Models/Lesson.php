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

    /**
     * @param array $results
     * @return array
     * @throws Exception
     */
    public function getSchedulesRelation(array $results): array
    {
        $ids = array_column($results, 'id');
        if (empty($ids))
            return $results;

        $schedules = (new Schedule())->get()
            ->where([
                'owner_type' => 'lesson',
                'owner_id' => ['in' => $ids]
            ])->all();

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
     * @return array
     * @throws Exception
     */
    public function getLecturerRelation(array $results): array
    {
        $userIds = array_unique(array_column($results, 'lecturer_id'));
        if (empty($userIds))
            return $results;

        $users = (new User())->get()->where(['id' => ['in' => $userIds]])->all();
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
     * @return array
     * @throws Exception
     */
    public function getDepartmentRelation(array $results): array
    {
        $deptIds = array_unique(array_column($results, 'department_id'));
        if (empty($deptIds))
            return $results;

        $departments = (new Department())->get()->where(['id' => ['in' => $deptIds]])->all();
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
     * @return array
     * @throws Exception
     */
    public function getProgramRelation(array $results): array
    {
        $progIds = array_unique(array_column($results, 'program_id'));
        if (empty($progIds))
            return $results;

        $programs = (new Program())->get()->where(['id' => ['in' => $progIds]])->all();
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
     * @return array
     * @throws Exception
     */
    public function getParentLessonRelation(array $results): array
    {
        $parentIds = array_unique(array_column($results, 'parent_lesson_id'));
        // remove nulls
        $parentIds = array_filter($parentIds);
        if (empty($parentIds))
            return $results;

        $lessons = (new Lesson())->get()->where(['id' => ['in' => $parentIds]])->all();
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
     * @return array
     * @throws Exception
     */
    public function getChildLessonsRelation(array $results): array
    {
        $ids = array_column($results, 'id');
        if (empty($ids))
            return $results;

        $lessons = (new Lesson())->get()->where(['parent_lesson_id' => ['in' => $ids]])->all();
        $lessonsGrouped = [];
        foreach ($lessons as $lesson) {
            $lessonsGrouped[$lesson->parent_lesson_id][] = $lesson;
        }

        foreach ($results as &$row) {
            $row['childLessons'] = $lessonsGrouped[$row['id']] ?? [];
        }
        return $results;
    }
    protected string $table_name = "lessons";

    /**
     * @return User|null
     * @throws Exception
     */
    public function getLecturer(): User|null
    {
        if (is_null($this->lecturer_id)) {
            return new User(); //hoca tanımlı değilse boş kullanıcı dön
        }
        return (new User())->find($this->lecturer_id);
    }

    /**
     * Dersin ait olduğu Bölüm/Department sınıfını döndürür
     * @return Department|null
     * @throws Exception
     */
    public function getDepartment(): Department|null
    {
        return (new Department())->find($this->department_id);
    }

    /**
     * Dersin ait olduğu program modelini döndürür
     * @return Program|null
     * @throws Exception
     */
    public function getProgram(): Program|null
    {
        return (new Program())->find($this->program_id);
    }

    /**
     * @return Lesson|null
     * @throws Exception
     */
    public function getParentLesson(): Lesson|null
    {
        return (new Lesson())->find($this->parent_lesson_id);

    }

    /**
     * @throws Exception
     */
    public function getChildLessonList(): array
    {
        return (new Lesson())->get()->where(["parent_lesson_id" => $this->id])->all();
    }

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
        $result = false;
        if ($type == "lesson") {
            //ders saati ile schedule programındaki satır saysı eşleşmiyorsa ders tamamlanmamış demektir
            $schedules = (new Schedule())->get()->where([
                'owner_id' => $this->id,
                'semester_no' => $this->semester_no,
                'owner_type' => 'lesson',
                'academic_year' => $this->academic_year,
                'type' => 'lesson',
                'semester' => $this->semester
            ])->all();
            $this->placed_hours = 0;
            foreach ($schedules as $schedule) {
                for ($i = 0; $i <= getSettingValue('maxDayIndex', 'lesson', 4); $i++) {
                    if (!is_null($schedule->{"day$i"})) {
                        $this->placed_hours++;
                    }
                }
            }

            if ($this->placed_hours == $this->hours) {
                $result = true;
            }
        } elseif ($type == "exam") {
            // İlgili dönem/yıl için sınıf sahibi (owner_type=classroom) exam kayıtlarını al ve ders bazında kapasite topla
            $examSchedules = (new Schedule())->get()->where([
                'owner_type' => 'classroom',
                'type' => 'exam',
                'semester' => $this->semester,
                'academic_year' => $this->academic_year,
            ])->all();

            // Ders bazında yerleştirilen kapasite
            $placedCapacityByLesson = [];
            $classroomCache = [];
            // maxExamDayIndex ayarı
            $maxExamDayIndex = getSettingValue('maxExamDayIndex', 'exam', 5);

            foreach ($examSchedules as $schedule) {
                // owner_id derslik id'sidir (sınıf sahibi kayıt)
                $classroomId = $schedule->owner_id;
                if (!isset($classroomCache[$classroomId])) {
                    $classroomCache[$classroomId] = (new Classroom())->find($classroomId);
                }
                $examSize = (int) ($classroomCache[$classroomId]->exam_size ?? 0);
                for ($i = 0; $i <= $maxExamDayIndex; $i++) {
                    $day = $schedule->{"day" . $i};
                    if (is_array($day)) {
                        if (isset($day[0]) && is_array($day[0])) {
                            foreach ($day as $grp) {
                                if (isset($grp['lesson_id'])) {
                                    $lid = (int) $grp['lesson_id'];
                                    $placedCapacityByLesson[$lid] = ($placedCapacityByLesson[$lid] ?? 0) + $examSize;
                                }
                            }
                        } else {
                            if (isset($day['lesson_id'])) {
                                $lid = (int) $day['lesson_id'];
                                $placedCapacityByLesson[$lid] = ($placedCapacityByLesson[$lid] ?? 0) + $examSize;
                            }
                        }
                    }
                }
            }

            // Kalan öğrenci sayısı = ders mevcudu - yerleştirilen toplam kapasite
            $this->placed_size = (int) ($placedCapacityByLesson[$this->id] ?? 0);
            $this->remaining_size = max(0, (int) $this->size - $this->placed_size);
            if ($this->remaining_size <= 0) {
                $result = true;
            }
        }

        return $result;
    }
}