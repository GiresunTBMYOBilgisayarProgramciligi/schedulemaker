<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Program;
use App\Repositories\ProgramRepository;
use App\Models\Schedule;
use Exception;
use PDO;
use PDOException;
use App\Core\Gate;
use App\DTOs\ProgramDTO;
use App\Validators\ProgramValidator;
use App\Services\ProgramService;
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
        return (new ProgramRepository())->findBy($filters);
    }

    /**
     * Yeni program oluşturur (POST /ajax/program/add rotası için)
     */
    public function store(array $requestData): array
    {
        try {
            Gate::authorize("create", Program::class, "Yeni program oluşturma yetkiniz yok");

            $validator = new ProgramValidator();
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            $dto = ProgramDTO::fromArray($requestData);
            (new ProgramService())->saveNew($dto);

            return [
                "status" => "success",
                "msg" => "Program başarıyla oluşturuldu."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Mevcut programı günceller (POST /ajax/program/update rotası için)
     */
    public function update(array $requestData): array
    {
        try {
            $program = clone (new Program())->find($requestData['id']);
            if (!$program) {
                throw new Exception("Güncellenecek program bulunamadı.");
            }

            Gate::authorize("update", $program, "Program güncelleme yetkiniz yok");

            $validator = new ProgramValidator();
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            $dto = ProgramDTO::fromArray($requestData);
            
            // DTO'dan Model'e aktar
            $program->fill(array_merge(['id' => $requestData['id']], $dto->toArray()));

            (new ProgramService())->updateProgram($program);

            return [
                "status" => "success",
                "msg" => "Program başarıyla güncellendi."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Programı siler (POST /ajax/program/delete rotası için)
     */
    public function destroy(array $requestData): array
    {
        try {
            if (empty($requestData['id'])) {
                throw new Exception("Silinecek program ID'si belirtilmedi.");
            }

            $program = clone (new Program())->find($requestData['id']);
            if (!$program) {
                throw new Exception("Silinecek program bulunamadı.");
            }

            Gate::authorize("delete", $program, "Program silme yetkiniz yok");

            (new ProgramService())->deleteProgram($program);

            return [
                "status" => "success",
                "msg" => "Program başarıyla silindi."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }
}