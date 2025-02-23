<?php

namespace App\Models;

use App\Controllers\ClassroomController;
use App\Core\Model;
use Exception;

class Classroom extends Model
{

    public ?int $id = null;
    public ?string $name = null;
    public ?int $class_size = null;
    public ?int $exam_size = null;
    /*
     * Sınıf Türü
     * 1-> Derslik
     * 2-> Bilgisayar laboratuvarı
     * 3-> Uzaktan Eğitim Sınıfı
     */
    public ?string $type = null;
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

    /**
     * @return string
     * @throws Exception
     */
    public function getTypeName(): string
    {
        try {
            return (new ClassroomController())->getTypeList()[$this->type] ?? "";
        } catch (Exception) {
            throw new Exception("Deslik türü alınamadı");
        }

    }
}