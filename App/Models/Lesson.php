<?php

namespace App\Models;

use App\Core\Model;

class Lesson extends Model
{
    public int $id;
    public string $name;
    public int $size;
    public Lecturer $lecturer;
    public Department $department;

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
            $this->name = $name;
            $this->size = $size;
            $this->lecturer = new Lecturer($lecturer_id);
            $this->department = new Department($department_id);
        }
    }
}