<?php

namespace App\Models;

use App\Controllers\UserController;
use App\Core\Model;
use PDO;
use PDOException;

class Department extends Model
{
    public ?int $id= null;
    public ?string $name= null;
    public ?int $chairperson_id= null;
    public ?int $schedule_id= null;

    private string $table_name = "departments";

    public function __construct(int $id = null)
    {
        parent::__construct(); # Connect to database
        if (isset($id)) {
            $q = $this->database->prepare("Select * From $this->table_name WHERE id=:id");
            $q->bindValue(":id", $id, PDO::PARAM_INT);
            $q->execute();
            $data = $q->fetch();
            extract($data);
            $this->id = $id;
            $this->name = $name;
            $this->schedule = $schedule_id;
            $this->chairperson_id = $chairperson_id;
        }
    }

    /**
     * @return User Chair Person
     */
    public function getChairperson():User
    {
        return (new UserController())->getUser($this->chairperson_id);
    }

}