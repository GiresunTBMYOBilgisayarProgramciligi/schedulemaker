<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;
use App\Repositories\DepartmentRepository;
use App\Models\Schedule;
use Exception;
use PDO;
use PDOException;
use App\Core\Gate;

class DepartmentController extends Controller
{
    protected string $table_name = "departments";
    protected string $modelName = "App\\Models\\Department";


    /**
     * Yeni bölüm oluşturur (POST /ajax/department/add rotası için)
     */
    public function store(array $requestData): array
    {
        try {
            Gate::authorize("create", Department::class, "Yeni bölüm oluşturma yetkiniz yok");

            $validator = new \App\Validators\DepartmentValidator();
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            $dto = \App\DTOs\DepartmentDTO::fromArray($requestData);
            (new \App\Services\DepartmentService())->saveNew($dto);

            return [
                "status" => "success",
                "msg" => "Bölüm başarıyla oluşturuldu."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Mevcut bölümü günceller (POST /ajax/department/update rotası için)
     */
    public function update(array $requestData): array
    {
        try {
            $department = clone (new Department())->find($requestData['id']);
            if (!$department) {
                throw new Exception("Güncellenecek bölüm bulunamadı.");
            }

            Gate::authorize("update", $department, "Bölüm güncelleme yetkiniz yok");

            $validator = new \App\Validators\DepartmentValidator();
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            $dto = \App\DTOs\DepartmentDTO::fromArray($requestData);
            
            // DTO'dan Model'e aktar
            $department->fill(array_merge(['id' => $requestData['id']], $dto->toArray()));

            (new \App\Services\DepartmentService())->updateDepartment($department);

            return [
                "status" => "success",
                "msg" => "Bölüm başarıyla güncellendi."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Bölümü siler (POST /ajax/department/delete rotası için)
     */
    public function destroy(array $requestData): array
    {
        try {
            if (empty($requestData['id'])) {
                throw new Exception("Silinecek bölüm ID'si belirtilmedi.");
            }

            $department = clone (new Department())->find($requestData['id']);
            if (!$department) {
                throw new Exception("Silinecek bölüm bulunamadı.");
            }

            Gate::authorize("delete", $department, "Bölüm silme yetkiniz yok");

            (new \App\Services\DepartmentService())->deleteDepartment($department);

            return [
                "status" => "success",
                "msg" => "Bölüm başarıyla silindi."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }
}