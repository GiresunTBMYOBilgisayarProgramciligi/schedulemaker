<?php

namespace App\Models;

use App\Core\Logger;
use App\Core\Model;
use Exception;
use PDO;
use PDOException;

class Program extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?int $department_id = null;

    private string $table_name = "programs";

    public function __construct(int $id = null)
    {
        parent::__construct(); # Connect to database
        if (isset($id)) {
            $q = $this->database->prepare("Select * From $this->table_name WHERE id=:id");
            $q->bindValue(":id", $id, PDO::PARAM_INT);
            $q->execute();
            $data = $q->fetch(PDO::FETCH_ASSOC);
            extract($data);
            $this->id = $id;
            $this->name = $name;
            $this->department_id = $department_id;
        }
    }

    /**
     * @return Department Programın bağlı olduğu Department sınıfı
     * @throws Exception
     */
    public function getDepartment(): Department
    {
        try {
            return new Department($this->department_id);
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
            $stmt = $this->database->prepare("Select * From users where program_id=:id");
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
            $stmt = $this->database->prepare("Select count(*) as count from users where program_id=:id");
            $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch();
            return $data['count'];
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
            $stmt = $this->database->prepare("Select count(*) as count from lessons where program_id=:id");
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
            $stmt = $this->database->prepare("Select * From lessons where program_id=:id");
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
    public function getStudentCount(): mixed
    {
        try {
            $stmt = $this->database->prepare("Select sum(size) as sum from lessons where program_id=:id");
            $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch();
            return $data['sum'];
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }


    }
}