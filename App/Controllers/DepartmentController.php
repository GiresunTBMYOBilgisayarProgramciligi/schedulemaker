<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;
use PDO;
use PDOException;
class DepartmentController extends Controller
{
    private string $table_name = "departments";

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
    public function getDepartments()
    {
        try {
            $q = $this->database->prepare("Select * From $this->table_name");
            $q->execute();
            return $q->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo $e->getMessage();
            return [];
        }
    }
}