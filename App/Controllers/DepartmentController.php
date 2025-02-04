<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;
use Exception;
use PDO;
use PDOException;

class DepartmentController extends Controller
{
    protected string $table_name = "departments";
    protected string $modelName = "App\Models\Department";

    /**
     * Belirtilen id değerine sahip Bölüm/Department Sınıfını döner. İd blirtilmemişse false döner
     * @param $id
     * @return Department|bool
     * @throws Exception
     */
    public function getDepartment($id): Department|bool
    {
        if (!is_null($id)) {
            try {
                $smtt = $this->database->prepare("select * from $this->table_name where id=:id");
                $smtt->bindValue(":id", $id, PDO::PARAM_INT);
                $smtt->execute();
                $departmentData = $smtt->fetch(\PDO::FETCH_ASSOC);
                if ($departmentData) {
                    $department = new Department();
                    $department->fill($departmentData);

                    return $department;
                } else throw new Exception("Department not found");
            } catch (Exception $e) {
                throw new $e;
            }
        }
        return false;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getDepartmentsList(): array
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
            return $departments;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Parametre olarak verilen modeli veri tabanına kaydeder
     * @param Department $new_department
     * @return int Veri tabanına eklenen Department id numarası
     * @throws Exception
     */
    public function saveNew(Department $new_department): int
    {
        try {
            $new_lesson_arr = $new_department->getArray(['table_name', 'database', 'id']);

            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_lesson_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_lesson_arr);
            return $this->database->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.", $e->getCode());
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param Department $department
     * @return int
     * @throws Exception
     */
    public function updateDepartment(Department $department):int
    {
        try {
            $departmentData = $department->getArray(['table_name', 'database', 'id']);
            // Sorgu ve parametreler için ayarlamalar
            $columns = [];
            $parameters = [];

            foreach ($departmentData as $key => $value) {
                $columns[] = "$key = :$key";
                $parameters[$key] = $value; // NULL dahil tüm değerler parametre olarak ekleniyor
            }

            // WHERE koşulu için ID ekleniyor
            $parameters["id"] = $department->id;

            // Dinamik SQL sorgusu oluştur
            $query = sprintf(
                "UPDATE %s SET %s WHERE id = :id",
                $this->table_name,
                implode(", ", $columns)
            );

            // Sorguyu hazırla ve çalıştır
            $stmt = $this->database->prepare($query);
            $stmt->execute($parameters);
            return $this->database->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                throw $e;
            }
        }
    }
}