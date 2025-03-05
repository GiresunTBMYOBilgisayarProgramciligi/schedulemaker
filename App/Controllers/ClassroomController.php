<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Classroom;
use App\Models\Schedule;
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
     * Tüm dersliklerin listesini döner
     * @return array
     * @throws Exception
     */
    public function getClassroomsList(): array
    {
        return $this->getListByFilters();
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
                throw new Exception("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
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
                throw new Exception("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
    }

    /**
     * @param int $id Silinecek dersin id numarası
     * @throws Exception
     */
    public function delete(int $id): void
    {
        // ilişkili tüm programı sil
        $schedules = (new Schedule())->get()->where(["owner_type" => "classroom", "owner_id" => $id])->all();
        foreach ($schedules as $schedule) {
            $schedule->delete();
        }
        (new Classroom())->find($id)->delete();
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