<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;
use PDO;
use PDOException;

class DepartmentController extends Controller
{
    protected string $table_name = "departments";

    public function getDepartment($id)
    {
        if (!is_null($id)) {
            try {
                $smtt = $this->database->prepare("select * from $this->table_name where id=:id");
                $smtt->bindValue(":id", $id, PDO::PARAM_INT);
                $smtt->execute();
                $department_data = $smtt->fetch(\PDO::FETCH_ASSOC);
                if ($department_data) {
                    $department = new Department();
                    $department->fill($department_data);

                    return $department;
                } else throw new \Exception("Department not found");
            } catch (\Exception $e) {
                // todo sitede bildirim şeklinde bir hata mesajı gösterip silsin. hata sınıfı oluşturarak ve session kullanarak mesajları gösterebilirim
                echo $e->getMessage();
            }
        }
    }

    public function getDepartmentsList()
    {
        try {
            $q = $this->database->prepare("Select * From $this->table_name");
            $q->execute();
            $department_list = $q->fetchAll(PDO::FETCH_ASSOC);
            $departments = [];
            foreach ($department_list as $departmentData) {
                $deparment = new Department();
                $deparment->fill($departmentData);
                $departments[] = $deparment;
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
            return [];
        }
        return $departments;
    }

    public function saveNew(Department $new_department): array
    {
        try {
            $new_lesson_arr = $new_department->getArray(['table_name', 'database', 'id']);

            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_lesson_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_lesson_arr);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];
    }

    public function updateDepartment(Department $department)
    {
        try {
            $departmentData = $department->getArray(['table_name', 'database', 'id']);
            $i = 0;
            $query = "UPDATE $this->table_name SET ";
            foreach ($departmentData as $k => $v) {
                if (is_null($v)) continue;
                if (++$i === count($departmentData)) $query .= $k . "=:" . $k . " ";
                else $query .= $k . "=:" . $k . ", ";
            }
            $query .= " WHERE id=:id";
            $departmentData["id"] = $department->id;
            $u = $this->database->prepare($query);
            $u->execute($departmentData);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }
        return ["status" => "success"];
    }
}