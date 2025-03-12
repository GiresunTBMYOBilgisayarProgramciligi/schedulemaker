<?php

namespace App\Models;

use App\Controllers\ClassroomController;
use App\Controllers\LessonController;
use App\Core\Model;
use Exception;

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
     * @return bool true if complete
     * @throws Exception
     */
    public function IsScheduleComplete(): bool
    {
        $result = false;
        //ders saati ile schedule programındaki satır saysı eşleşmiyorsa ders tamamlanmamış demektir
        $schedules = (new Schedule())->get()->where([
            'owner_id' => $this->id,
            'semester_no' => $this->semester_no,
            'owner_type' => 'lesson',
            'academic_year' => $this->academic_year,
            'type' => 'lesson',
            'semester' => $this->semester
        ])->all();
        if (count($schedules) == $this->hours) {
            $result = true;
        }
        return $result;
    }
}