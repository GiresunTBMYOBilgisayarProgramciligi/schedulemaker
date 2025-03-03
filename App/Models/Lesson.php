<?php

namespace App\Models;

use App\Controllers\ClassroomController;
use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Core\Logger;
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

    private string $table_name = "lessons";

    public function __construct()
    {
    }

    /**
     * @return User|null
     * @throws Exception
     */
    public function getLecturer(): User|null
    {
        try {
            if (is_null($this->lecturer_id)) {
                return new User(); //hoca tanımlı değilse boş kullanıcı dön
            }
            return (new UserController())->getUser($this->lecturer_id);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }

    }

    /**
     * Dersin ait olduğu Bölüm/Department sınıfını döndürür
     * @return Department|null
     * @throws Exception
     */
    public function getDepartment(): Department|null
    {
        try {
            return (new DepartmentController())->getDepartment($this->department_id);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Dersin ait olduğu program modelini döndürür
     * @return Program|null
     * @throws Exception
     */
    public function getProgram(): Program|null
    {
        try {
            return (new ProgramController())->getProgram($this->program_id);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public function getFullName(): string
    {
        return $this->name . " (" . $this->code . ")";
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getClassroomTypeName(): string
    {
        try {
            return (new ClassroomController())->getTypeList()[$this->classroom_type] ?? "";
        } catch (Exception) {
            throw new Exception("Deslik türü alınamadı");
        }
    }
    public function getTypeName(): string
    {
        try {
            return (new LessonController())->getTypeList()[$this->type] ?? "";
        } catch (Exception) {
            throw new Exception("Ders türü alınamadı");
        }
    }
}