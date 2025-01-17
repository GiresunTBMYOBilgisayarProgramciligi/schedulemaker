<?php

namespace App\Models;

use App\Controllers\DepartmentController;
use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Core\Model;
use PDO;
use PDOException;

class Lesson extends Model
{
    public ?int $id = null;
    public ?string $code = null;
    public ?string $name = null;
    public ?int $size = null;
    public ?int $hours = 2;
    public ?string $type = null;
    public ?string $season = null;
    public ?int $lecturer_id = null;
    public ?int $department_id = null;
    public ?int $program_id = null;

    private string $table_name = "lessons";

    /**
     * @param int $id
     */
    public function __construct()
    {
        parent::__construct(); # Connect to database
    }

    /**
     * @return User Chair Person
     */
    public function getLecturer(): User|null
    {
        return (new UserController())->getUser($this->lecturer_id);
    }

    public function getDepartment(): Department|null
    {
        return (new DepartmentController())->getDepartment($this->department_id);
    }

    public function getProgam(): Program|null
    {
        return (new ProgramController())->getProgram($this->program_id);
    }

    public function getFullName(): string
    {
        return $this->name . " (" . $this->code . ")";
    }
}