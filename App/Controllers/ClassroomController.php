<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Classroom;
use PDO;
use PDOException;
class ClassroomController extends Controller
{
    private string $table_name = "lessons";

    public function getClass($id)
    {
        if (!is_null($id)) {
            try {
                $stmt = $this->database->prepare("select * from $this->table_name where id=:id");
                $stmt->bindValue(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($stmt) {
                    $classroom = new Classroom();
                    $classroom->fill($stmt);

                    return $classroom;
                } else throw new \Exception("Classroom not found");
            } catch (\Exception $e) {
                // todo sitede bildirim şeklinde bir hata mesajı gösterip silsin.
                echo $e->getMessage();
            }
        }
    }

    public function getClassroomsList()
    {
        $q = $this->database->prepare("SELECT * FROM $this->table_name ");
        $q->execute();
        $classrooms = $q->fetchAll(PDO::FETCH_ASSOC);
        $classrooms = [];
        foreach ($classrooms as $classroom_data) {
            $classroom = new Classroom();
            $classroom->fill($classroom_data);
            $classrooms[] = $classroom;
        }
        return $classrooms;
    }
}