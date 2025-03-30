<?php

namespace App\Models;

use App\Core\Model;
use Exception;
use PDO;

class Program extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?int $department_id = null;

    protected string $table_name = "programs";


    /**
     * @return Department|null
     * @throws Exception
     */
    public function getDepartment(): Department|null
    {
        return (new Department)->find($this->department_id);

    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLecturers(): array
    {
        $userModel = new User();
        return $userModel->get()->where(['program_id' => $this->id])->all();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLecturerCount(): mixed
    {
        $userModel = new User();
        return $userModel->get()->where(['program_id' => $this->id])->count();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLessonCount(): mixed
    {
        $lessonModel = new Lesson();
        return $lessonModel->get()->where(['program_id' => $this->id])->count();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLessons(): array
    {
        $lessonModel = new Lesson();
        return $lessonModel->get()->where(['program_id' => $this->id])->all();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getStudentCount(): mixed
    {
        $lessonModel = new Lesson();
        return $lessonModel->get()->where(['program_id' => $this->id])->sum("size");
    }
}