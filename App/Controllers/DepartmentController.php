<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;
use Exception;
use PDO;
use PDOException;
use function App\Helpers\isAuthorized;

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
            $stmt = $this->database->prepare("select * from $this->table_name where id=:id");
            $stmt->bindValue(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            $departmentData = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($departmentData) {
                $department = new Department();
                $department->fill($departmentData);

                return $department;
            } else throw new Exception("Bölüm bulunamadı");
        }
        return false;
    }

    /**
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function getDepartmentsList(array $filters = []): array
    {
        if (!isAuthorized("submanager")) {
            $filters['id'] = (new UserController())->getCurrentUser()->department_id;
        }
        return $this->getListByFilters($filters);
    }

    /**
     * @param string $name
     * @return Department|bool
     * @throws Exception
     */
    public function getDepartmentByName(string $name): Department|bool
    {
        return $this->getListByFilters(["name" => $name])[0] ?? false;
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
            if (!isAuthorized("submanager")) {
                throw new Exception("Yeni Bölüm oluşturma yetkiniz yok");
            }

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
                throw new Exception("Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
    }

    /**
     * @param Department $department
     * @return int
     * @throws Exception
     */
    public function updateDepartment(Department $department): int
    {
        try {
            if (!isAuthorized("submanager", false, $department)) {
                throw new Exception("Bölüm Güncelleme yetkiniz yok");
            }

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
            return $department->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
    }
}