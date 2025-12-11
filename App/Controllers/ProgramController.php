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
     * @param array $filters department_id Bölüm id numarası belirtilirse sadece o bölüme ait programlar listelenir
     * @return array
     * @throws Exception
     */
    public function getProgramsList(array $filters = []): array
    {
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
     * @param array $program_data
     * @return int
     * @throws Exception
     */
    public function updateProgram(array $program_data): int
    {
        try {
            $program= new Program();
            $program->fill($program_data);
            $program->update();
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