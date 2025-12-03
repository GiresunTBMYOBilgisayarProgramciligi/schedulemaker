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
                for ($i = 0; $i <= getSettingValue('maxDayIndex', default: 4); $i++) {
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
            $maxExamDayIndex = getSettingValue('maxExamDayIndex', default: 5);
            foreach ($examSchedules as $schedule) {
                // owner_id derslik id'sidir (sınıf sahibi kayıt)
                $classroomId = $schedule->owner_id;
                if (!isset($classroomCache[$classroomId])) {
                    $classroomCache[$classroomId] = (new Classroom())->find($classroomId);
                }
                $examSize = (int)($classroomCache[$classroomId]->exam_size ?? 0);
                for ($i = 0; $i <= $maxExamDayIndex; $i++) {
                    $day = $schedule->{"day" . $i};
                    if (is_array($day)) {
                        if (isset($day[0]) && is_array($day[0])) {
                            foreach ($day as $grp) {
                                if (isset($grp['lesson_id'])) {
                                    $lid = (int)$grp['lesson_id'];
                                    $placedCapacityByLesson[$lid] = ($placedCapacityByLesson[$lid] ?? 0) + $examSize;
                                }
                            }
                        } else {
                            if (isset($day['lesson_id'])) {
                                $lid = (int)$day['lesson_id'];
                                $placedCapacityByLesson[$lid] = ($placedCapacityByLesson[$lid] ?? 0) + $examSize;
                            }
                        }
                    }
                }
            }

            // Kalan öğrenci sayısı = ders mevcudu - yerleştirilen toplam kapasite
            $this->placed_size = (int)($placedCapacityByLesson[$this->id] ?? 0);
            $this->remaining_size = max(0, (int)$this->size - $this->placed_size);
            if ($this->remaining_size <= 0) {
                $result = true;
            }
        }

        return $result;
    }
}