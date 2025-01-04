<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Classroom;
use PDO;
use PDOException;
class ClassroomController extends Controller
{
    protected string $table_name = "classrooms";

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
        $classrooms_list = $q->fetchAll(PDO::FETCH_ASSOC);
        $classrooms = [];
        foreach ($classrooms_list as $classroom_data) {
            $classroom = new Classroom();
            $classroom->fill($classroom_data);
            $classrooms[] = $classroom;
        }
        return $classrooms;
    }

    public function saveNew(Classroom $new_classroom): array
    {
        try {
            $q = $this->database->prepare(
                "INSERT INTO $this->table_name(name, class_size, exam_size) 
            values  (:name, :class_size, :exam_size)");
            if ($q) {
                $new_classroom_arr = $new_classroom->getArray(['table_name', 'database', 'id' ]);
                $q->execute($new_classroom_arr);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];
    }

    public function updateClassroom(Classroom $classroom)
    {
        try {
            $classroomData = $classroom->getArray(['table_name', 'database', 'id']);
            $i = 0;
            $query = "UPDATE $this->table_name SET ";
            foreach ($classroomData as $k => $v) {
                if (is_null($v)) continue;
                if (++$i === count($classroomData)) $query .= $k . "=:" . $k . " ";
                else $query .= $k . "=:" . $k . ", ";
            }
            $query .= " WHERE id=:id";
            $classroomData["id"] = $classroom->id;
            $u = $this->database->prepare($query);
            $u->execute($classroomData);

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }
        return ["status" => "success"];
    }
}