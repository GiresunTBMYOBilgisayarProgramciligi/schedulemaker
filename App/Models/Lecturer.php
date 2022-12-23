<?php

namespace App\Models;

use App\Core\Model;

class Lecturer extends Model
{

    public int $id;
    public string $name;
    public string $last_name;
    public string $title;
    public Department $department;
    public object $schedule;

    private string $table_name = "lecturers";

    public function __construct($id)
    {
        parent::__construct(); # Connect to database
        $q = $this->database->prepare("Select * From $this->table_name WHERE id=:id");
        $q->execute(["id" => $id]);
        $data = $q->fetchAll();
        extract($data);
        $this->id = $id;
        $this->last_name = $last_name;
        $this->name = $name;
        $this->department = new Department($department_id);
        $this->schedule = $schedule;

    }
}