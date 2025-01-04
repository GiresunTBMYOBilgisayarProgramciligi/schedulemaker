<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Program;
use PDO;
use PDOException;

class ProgramController extends Controller
{
    protected string $table_name = "programs";

    public function getProgram($id)
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
                } else throw new \Exception("Department not found");
            } catch (\Exception $e) {
                // todo sitede bildirim şeklinde bir hata mesajı gösterip silsin.
                echo $e->getMessage();
            }
        }
    }

    /**
     * @param int | null $department_id Bölüm id numarası belirtilirse sadece o bölüme ait programlar listelenir
     * @return array
     */
    public function getProgramsList($department_id = null)
    {
        try {
            if (!is_null($department_id)) {
                $q = $this->database->prepare("Select * From $this->table_name WHERE department_id=:department_id");
                $q->bindValue(":department_id", $department_id, PDO::PARAM_INT);
                $q->execute();
            } else {
                $q = $this->database->prepare("Select * From $this->table_name");
                $q->execute();
            }
            $programs_list = $q->fetchAll(PDO::FETCH_ASSOC);
            $programs = [];
            foreach ($programs_list as $programData) {
                $program = new Program();
                $program->fill($programData);
                $programs[] = $program;
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
            return [];
        }
        return $programs;
    }
    public function saveNew(Program $new_program): array
    {
        try {
            $q = $this->database->prepare(
                "INSERT INTO $this->table_name(name,  department_id) 
            values  (:name, :department_id)");
            if ($q) {
                $new_program_arr = $new_program->getArray(['table_name', 'database', 'id' ]);
                $q->execute($new_program_arr);
            }
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