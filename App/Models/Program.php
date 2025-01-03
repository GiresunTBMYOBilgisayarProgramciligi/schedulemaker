<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class Program extends Model
{
    public ?int $id= null;
    public ?string $name= null;
    public ?int $department_id= null;
    public ?int $schedule_id= null;

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
            $this->schedule_id = $schedule_id;
        }
    }

    /**
     * @return Department Programın bağlı olduğu Department sınıfı
     */
    public function getDepartment():Department
    {
        return new Department($this->department_id);
    }

}