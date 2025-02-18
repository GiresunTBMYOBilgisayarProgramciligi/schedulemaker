<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Program;
use Exception;
use PDO;
use PDOException;
use function App\Helpers\isAuthorized;

class ProgramController extends Controller
{
    protected string $table_name = "programs";
    protected string $modelName = "App\Models\Program";

    /**
     * id numarası verilen program modelini döndürür
     * @param $id
     * @return Program|null
     * @throws Exception
     */
    public function getProgram($id): Program|null
    {
        if (!is_null($id)) {
            try {
                $stmt = $this->database->prepare("select * from $this->table_name where id=:id");
                $stmt->bindValue(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $program_data = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($program_data) {
                    $program = new Program();
                    $program->fill($program_data);

                    return $program;
                } else throw new Exception("Program not found");
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
        return null;
    }

    /**
     * @param int | null $department_id Bölüm id numarası belirtilirse sadece o bölüme ait programlar listelenir
     * @return array
     * @throws Exception
     */
    public function getProgramsList(?int $department_id = null): array
    {
        try {
            $filters = [];
            if (!is_null($department_id)) $filters["department_id"] = $department_id;
            return $this->getListByFilters($filters);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param Program $new_program
     * @return int
     * @throws Exception
     */
    public function saveNew(Program $new_program): int
    {
        try {
            if (!isAuthorized("submanager"))
                throw new Exception("Bu işlemi yapmak için yetkiniz yok");
            $new_program_arr = $new_program->getArray(['table_name', 'database', 'id']);
            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_program_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_program_arr);
            return $this->database->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde Program zaten kayıtlı. Lütfen farklı bir isim giriniz.", $e->getCode(), $e);
            } else {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
    }

    /**
     * @param Program $program
     * @return int
     * @throws Exception
     */
    public function updateProgram(Program $program):int
    {
        try {
            if (!isAuthorized("submanager",false,$program))
                throw new Exception("Bu işlemi yapmak için yetkiniz yok");
            $programData = $program->getArray(['table_name', 'database', 'id']);
            // Sorgu ve parametreler için ayarlamalar
            $columns = [];
            $parameters = [];

            foreach ($programData as $key => $value) {
                $columns[] = "$key = :$key";
                $parameters[$key] = $value; // NULL dahil tüm değerler parametre olarak ekleniyor
            }

            // WHERE koşulu için ID ekleniyor
            $parameters["id"] = $program->id;

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
                return $program->id;
            } else throw new Exception("Program Güncellenemedi");
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde prgoram zaten kayıtlı. Lütfen farklı bir isim giriniz.",$e->getCode(), $e);
            } else {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
    }
}