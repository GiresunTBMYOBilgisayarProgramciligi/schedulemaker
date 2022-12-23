<?php

namespace App\Models;

use App\Core\Model;

class Classroom extends Model
{

    public int $id;
    public string $name;
    public object $schedule;
    public int $class_size;
    public int $exam_size;

    private string $table_name = "classrooms";

    public function __construct($id)
    {
        parent::__construct(); # Connect to database
        $q = $this->database->prepare("Select * From $this->table_name WHERE id=:id");
        $q->execute(["id" => $id]);
        $data = $q->fetchAll();
        extract($data);
        $this->id = $id;
        $this->name = $name;
        $this->schedule = $schedule;
        $this->class_size = $class_size;
        $this->exam_size = $exam_size;
    }
}