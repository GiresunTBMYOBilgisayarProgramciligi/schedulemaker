<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Classroom;
use PDO;
use PDOException;
use Exception;
use function App\Helpers\isAuthorized;

/**
 * Controller sınıfından türetilmiştir. Derslikler ile ilgili işlemleri yönetir.
 */
class ClassroomController extends Controller
{
    protected string $table_name = "classrooms";
    protected string $modelName = "App\Models\Classroom";

    /**
     * @param $id
     * @return Classroom
     * @throws Exception
     */
    public function getClassroom($id): Classroom
    {
        if (!is_null($id)) {
            try {
                $stmt = $this->database->prepare("select * from $this->table_name where id=:id");
                $stmt->bindValue(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $classroomData = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($classroomData) {
                    $classroom = new Classroom();
                    $classroom->fill($classroomData);

                    return $classroom;
                } else throw new Exception("Classroom not found");
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        } else throw new Exception("id belirtilmelidir");
    }

    /**
     * Tüm dersliklerin listesini döner
     * @return array
     * @throws Exception
     */
    public function getClassroomsList(): array
    {
        try {
            return $this->getListByFilters();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Veri tabanında yeni bir derslik oluşturur
     * @param Classroom $new_classroom
     * @return int eklenen derslikid numarası
     * @throws Exception
     */
    public function saveNew(Classroom $new_classroom): int
    {
        try {
            if (!isAuthorized("submanager"))
                throw new Exception("Bu işlem için yetkiniz yok.");
            $new_classroom_arr = $new_classroom->getArray(['table_name', 'database', 'id']);

            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_classroom_arr);
            $q = $this->database->prepare($sql);
            $q->execute($new_classroom_arr);
            return $this->database->lastInsertId();
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                error_log($e->getMessage());
                throw new Exception("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }

    }

    /**
     * Belirtilen verilere göre veri tabanında derslik bilgilerini günceller
     * @param Classroom $classroom
     * @return int Güncellenen derslik idsi
     * @throws Exception
     */
    public function updateClassroom(Classroom $classroom): int
    {
        try {
            if (!isAuthorized("submanager"))
                throw new Exception("Bu işlem için yetkiniz yok.");
            $classroomData = $classroom->getArray(['table_name', 'database', 'id'], true);
            // Sorgu ve parametreler için ayarlamalar
            $columns = [];
            $parameters = [];

            foreach ($classroomData as $key => $value) {
                $columns[] = "$key = :$key";
                $parameters[$key] = $value; // NULL dahil tüm değerler parametre olarak ekleniyor
            }

            // WHERE koşulu için ID ekleniyor
            $parameters["id"] = $classroom->id;

            // Dinamik SQL sorgusu oluştur
            $query = sprintf(
                "UPDATE %s SET %s WHERE id = :id",
                $this->table_name,
                implode(", ", $columns)
            );

            // Sorguyu hazırla ve çalıştır
            $stmt = $this->database->prepare($query);
            $stmt->execute($parameters);
            if ($stmt->rowCount() > 0) {
                return $classroom->id;
            } else throw new Exception("Derslik Güncellenemedi");
        } catch (PDOException $e) {
            error_log($e->getMessage());
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
    }
}