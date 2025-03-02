<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
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
                } else {
                    Logger::setErrorLog("Derslik Bulunamadı");
                    throw new Exception("Derslik Bulunamadı");
                }
            } catch (Exception $e) {
                Logger::setExceptionLog($e);
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        } else {
            Logger::setErrorLog("İd belirtilmelidir");
            throw new Exception("İd belirtilmelidir");
        }
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
            Logger::setExceptionLog($e);
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
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
            if (!isAuthorized("submanager")) {
                Logger::setErrorLog("Yeni derslik oluşturma yetkiniz yok");
                throw new Exception("Yeni derslik oluşturma yetkiniz yok");
            }

            $new_classroom_arr = $new_classroom->getArray(['table_name', 'database', 'id']);

            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_classroom_arr);
            $q = $this->database->prepare($sql);
            $q->execute($new_classroom_arr);
            return $this->database->lastInsertId();
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                Logger::setErrorLog("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
                throw new Exception("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                Logger::setExceptionLog($e);
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
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
            if (!isAuthorized("submanager")) {
                Logger::setErrorLog("Derslik güncelleme yetkiniz yok");
                throw new Exception("Derslik güncelleme yetkiniz yok");
            }

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
            return $classroom->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                Logger::setErrorLog("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
                throw new Exception("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                Logger::setExceptionLog($e);
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
    }

    public function getTypeList(): array
    {
        return [
            1 => "Derslik",
            2 => "Bilgisayar Laboratuvarı",
            3 => "Uzaktan Eğitim Sınıfı"
        ];
    }
}