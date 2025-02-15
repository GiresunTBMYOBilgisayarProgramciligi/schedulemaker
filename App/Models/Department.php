<?php

namespace App\Models;

use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Core\Model;
use Exception;
use PDO;
use PDOException;

class Department extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?int $chairperson_id = null;

    private string $table_name = "departments";

    public function __construct(int $id = null)
    {
        parent::__construct(); # Connect to database
        if (isset($id)) {
            $q = $this->database->prepare("Select * From $this->table_name WHERE id=:id");
            $q->bindValue(":id", $id, PDO::PARAM_INT);
            $q->execute();
            $data = $q->fetch();
            extract($data);
            $this->id = $id;
            $this->name = $name;
            $this->schedule = $schedule_id;
            $this->chairperson_id = $chairperson_id;
        }
    }

    /**
     * @return User Chair Person
     * @throws Exception
     */
    public function getChairperson(): User
    {
        try {
            return (new UserController())->getUser($this->chairperson_id);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }

    }

    public function getProgramCount()
    {
        $stmt = $this->database->prepare("Select count(*) as count from programs where department_id=:id");
        $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch();
        return $data['count'];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getPrograms(): array
    {
        try {
            $stmt = $this->database->prepare("Select * From programs where department_id=:id");
            $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $programs = $stmt->fetchAll();
            $programs_list = array();
            foreach ($programs as $programData) {
                $program = new Program();
                $program->fill($programData);
                $programs_list[] = $program;
            }
            return $programs_list;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }

    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLecturers(): array
    {
        try {
            $stmt = $this->database->prepare("Select * From users where department_id=:id");
            $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $lecturers = $stmt->fetchAll();
            $lecturers_list = array();
            foreach ($lecturers as $lecturerData) {
                $lecturer = new User();
                $lecturer->fill($lecturerData);
                $lecturers_list[] = $lecturer;
            }
            return $lecturers_list;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }

    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLecturerCount(): mixed
    {
        try {
            $stmt = $this->database->prepare("Select count(*) as count from users where department_id=:id");
            $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch();
            return $data['count'];
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }

    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLessons(): array
    {
        try {
            $stmt = $this->database->prepare("Select * From lessons where department_id=:id");
            $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $lessons = $stmt->fetchAll();
            $lessons_list = array();
            foreach ($lessons as $lessonData) {
                $lesson = new Lesson();
                $lesson->fill($lessonData);
                $lessons_list[] = $lesson;
            }
            return $lessons_list;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }

    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLessonCount(): mixed
    {
        try {
            $stmt = $this->database->prepare("Select count(*) as count from lessons where department_id=:id");
            $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch();
            return $data['count'];
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }

    }
}