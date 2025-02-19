<?php

namespace App\Models;

use App\Controllers\DepartmentController;
use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Core\Model;
use Exception;

class Lesson extends Model
{
    public ?int $id = null;
    public ?string $code = null;
    public ?string $name = null;
    public ?int $size = null;
    public ?int $hours = 2;
    public ?string $type = null;
    public ?int $semester_no = null;
    public ?int $lecturer_id = null;
    public ?int $department_id = null;
    public ?int $program_id = null;

    private string $table_name = "lessons";

    public function __construct()
    {
        parent::__construct(); # Connect to database
    }

    /**
     * @return User|null
     * @throws Exception
     */
    public function getLecturer(): User|null
    {
        try {
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
}