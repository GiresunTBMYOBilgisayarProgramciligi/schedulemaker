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
     * 4-> Karma (Derslik ve Lab)
     */
    public ?string $type = null;
    protected string $table_name = "classrooms";


    /**
     * @return string
     * @throws Exception
     */
    public function getTypeName(): string
    {
        return (new ClassroomController())->getTypeList()[$this->type] ?? "";
    }
}