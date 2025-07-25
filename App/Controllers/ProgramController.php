<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Program;
use App\Models\Schedule;
use Exception;
use PDO;
use PDOException;

class ProgramController extends Controller
{
    protected string $table_name = "programs";
    protected string $modelName = "App\Models\Program";


    /**
     * @param int | null $department_id Bölüm id numarası belirtilirse sadece o bölüme ait programlar listelenir
     * @return array
     * @throws Exception
     */
    public function getProgramsList(?int $department_id = null): array
    {
        $filters = [];
        if (!is_null($department_id)) $filters["department_id"] = $department_id;
        return $this->getListByFilters($filters);
    }

    /**
     * @param string $name
     * @return Program|bool
     * @throws Exception,
     */
    public function getProgramByName(string $name): Program|bool
    {
        return $this->getListByFilters(["name" => $name])[0] ?? false;

    }

    /**
     * @param Program $new_program
     * @return int
     * @throws Exception
     */
    public function saveNew(Program $new_program): int
    {
        try {
            $new_program->create();
            return $new_program->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde Program zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
    }

    /**
     * @param Program $program
     * @return int
     * @throws Exception
     */
    public function updateProgram(Program $program): int
    {
        try {
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
            return $program->id;
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde prgoram zaten kayıtlı. Lütfen farklı bir isim giriniz.");
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
        $program = (new Program())->find($id) ?: throw new Exception("Silinecek Program bulunamadı");
        // ilişkili tüm programı sil //todo bu silme işlemi findLessonSchedules da olduğu gibi olmalı
        $schedules = (new Schedule())->get()->where(["owner_type" => "program", "owner_id" => $id])->all();
        foreach ($schedules as $schedule) {
            $schedule->delete();
        }
        $program->delete();
    }
}