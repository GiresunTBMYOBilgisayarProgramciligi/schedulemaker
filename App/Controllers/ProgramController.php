<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Program;
use Exception;
use PDO;
use PDOException;

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
                $smtt = $this->database->prepare("select * from $this->table_name where id=:id");
                $smtt->bindValue(":id", $id, PDO::PARAM_INT);
                $smtt->execute();
                $program_data = $smtt->fetch(\PDO::FETCH_ASSOC);
                if ($program_data) {
                    $program = new Program();
                    $program->fill($program_data);

                    return $program;
                } else throw new Exception("Department not found");
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

    public function saveNew(Program $new_program): array
    {
        try {
            $new_program_arr = $new_program->getArray(['table_name', 'database', 'id']);
            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_program_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_program_arr);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu isimde Program zaten kayıtlı. Lütfen farklı bir isim giriniz." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];
    }

    /**
     * @param Program $program
     * @return string[]
     * todo userController daki gibi güncelle
     */
    public function updateProgram(Program $program)
    {
        try {
            $programData = $program->getArray(['table_name', 'database', 'id']);
            $i = 0;
            $query = "UPDATE $this->table_name SET ";
            foreach ($programData as $k => $v) {
                if (is_null($v)) continue;
                if (++$i === count($programData)) $query .= $k . "=:" . $k . " ";
                else $query .= $k . "=:" . $k . ", ";
            }
            $query .= " WHERE id=:id";
            $programData["id"] = $program->id;
            $u = $this->database->prepare($query);
            $u->execute($programData);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu isimde prgoram zaten kayıtlı. Lütfen farklı bir isim giriniz." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }
        return ["status" => "success"];
    }
}