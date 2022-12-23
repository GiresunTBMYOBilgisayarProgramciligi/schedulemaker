<?php

namespace App\Models;

use App\Core\Model;

class Department extends Model
{
    public int $id;
    public string $name;
    public Lecturer $chairperson;
    public object $schedule;

    private string $table_name="departments";

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
        $this->chairperson = new Lecturer($chairperson_id);
    }

}