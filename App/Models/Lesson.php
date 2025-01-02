<?php

namespace App\Models;

use App\Controllers\UserController;
use App\Core\Model;
use PDO;
use PDOException;

class Lesson extends Model
{
    public int $id;
    public string $code;
    public string $name;
    public int $size;
    public int $lecturer_id;
    public int $department_id;

    private string $table_name = "lessons";

    /**
     * @param int $id
     */
    public function __construct(int $id = null)
    {
        parent::__construct(); # Connect to database
        if (isset($id)) {
            $q = $this->database->prepare("Select * From $this->table_name WHERE id=:id");
            $q->execute(["id" => $id]);
            $data = $q->fetchAll();
            extract($data);
            $this->id = $id;
            $this->code = $code;
            $this->name = $name;
            $this->size = $size;
            $this->lecturer_id = $lecturer_id;
            $this->departmen_id = $department_id;
        }
    }

    /**
     * @return User Chair Person
     */
    public function getLecturer():User
    {
        return (new UserController())->getUser($this->lecturer_id);
    }

    /**
     * @return array
     */
    public function getLessons()
    {
        try {
            $q = $this->database->prepare("Select * From $this->table_name");
            $q->execute();
            return $q->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo $e->getMessage();
            return [];
        }
    }
}