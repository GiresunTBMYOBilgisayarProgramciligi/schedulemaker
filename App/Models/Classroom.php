<?php

namespace App\Models;

use App\Core\Model;

class Classroom extends Model
{

    public ?int $id= null;
    public ?string $name= null;
    public ?int $class_size= null;
    public ?int $exam_size= null;
    /*
     * todo tür ve özellikler şeklinde iki alan eklenebilir.
     * Tür lab sınıf amfi konferans salonu
     * Özellikler beyaz tahta, akıllı tahta, projeksiyon var yok, kara tahta
     */
    private string $table_name = "classrooms";

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
            $this->schedule = $schedule;
            $this->class_size = $class_size;
            $this->exam_size = $exam_size;
        }
    }
}