<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Program;
use PDO;
use PDOException;

class ProgramController extends Controller
{
    private string $table_name = "programs";

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

    public function getProgramsList($department_id = null)
    {
        try {
            if (!is_null($department_id)) {
                $q = $this->database->prepare("Select * From $this->table_name WHERE department_id=:department_id");
                $q->execute(["department_id"=>$department_id]);
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
}