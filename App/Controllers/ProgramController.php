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
    public function getPrograms()
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