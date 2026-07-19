<?php

namespace App\Controllers;

use App\Enums\PermissionType;

use App\Core\Controller;
use App\Models\Program;
use App\Repositories\ProgramRepository;
use App\Models\Schedule;
use Exception;
use App\Exceptions\ValidationException;
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
     * AjaxRouter için program listesi döner
     */
    public function getProgramsListResponse(int $department_id): array
    {            $programs = $this->getProgramsList(['department_id' => $department_id, 'active' => true]);
            return [
                'status' => "success",
                'programs' => $programs
            ];
    }

    /**
     * Yeni program oluşturur (POST /ajax/program/add rotası için)
     */
    public function store(array $requestData): array
    {            Gate::authorize(PermissionType::CREATE->value, Program::class, "Yeni program oluşturma yetkiniz yok");

            $dto = (new ProgramValidator())->getDTO($requestData);
            (new ProgramService())->saveNew($dto);

            return [
                "status" => "success",
                "msg" => "Program başarıyla oluşturuldu."
            ];
    }

    /**
     * Mevcut programı günceller (POST /ajax/program/update rotası için)
     */
    public function update(array $requestData): array
    {            $program = clone (new Program())->find($requestData['id']);
            if (!$program) {
                throw new Exception("Güncellenecek program bulunamadı.");
            }

            Gate::authorize(PermissionType::UPDATE->value, $program, "Program güncelleme yetkiniz yok");

            $dto = (new ProgramValidator())->getDTO($requestData);
            
            // DTO'dan Model'e aktar
            $program->fill(array_merge(['id' => $requestData['id']], $dto->toArray()));

            (new ProgramService())->updateProgram($program);

            return [
                "status" => "success",
                "msg" => "Program başarıyla güncellendi."
            ];
    }

    /**
     * Programı siler (POST /ajax/program/delete rotası için)
     */
    public function destroy(array $requestData): array
    {            if (empty($requestData['id'])) {
                throw new Exception("Silinecek program ID'si belirtilmedi.");
            }

            $program = clone (new Program())->find($requestData['id']);
            if (!$program) {
                throw new Exception("Silinecek program bulunamadı.");
            }

            Gate::authorize(PermissionType::DELETE->value, $program, "Program silme yetkiniz yok");

            (new ProgramService())->deleteProgram($program);

            return [
                "status" => "success",
                "msg" => "Program başarıyla silindi."
            ];
    }
}