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

    protected string $table_name = "departments";

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
            $this->chairperson_id = $chairperson_id;
        }
    }

    /**
     * Bölüm başkanı Modelini döner. Eğer bölüm başkanı tanımlı değilse Boş Model döner
     * @return User Chair Person
     * @throws Exception
     */
    public function getChairperson(): User
    {
        if (is_null($this->chairperson_id)) {
            return new User(); // bölüm başkanı tanımlı değilse boş kullanıcı döndür.
        } else
            return (new UserController())->getUser($this->chairperson_id);
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
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLecturers(): array
    {
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
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLecturerCount(): mixed
    {
        $stmt = $this->database->prepare("Select count(*) as count from users where department_id=:id");
        $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch();
        return $data['count'];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLessons(): array
    {
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
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLessonCount(): mixed
    {
        $stmt = $this->database->prepare("Select count(*) as count from lessons where department_id=:id");
        $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch();
        return $data['count'];
    }
}