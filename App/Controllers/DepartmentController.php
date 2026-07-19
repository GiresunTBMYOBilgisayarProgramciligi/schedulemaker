<?php

namespace App\Controllers;

use App\Enums\PermissionType;

use App\Core\Controller;
use App\Models\Department;
use App\Repositories\DepartmentRepository;
use App\Models\Schedule;
use Exception;
use App\Exceptions\ValidationException;
use PDO;
use PDOException;
use App\Core\Gate;
use App\DTOs\DepartmentDTO;
use App\Validators\DepartmentValidator;
use App\Services\DepartmentService;
class DepartmentController extends Controller
{
    protected string $table_name = "departments";
    protected string $modelName = "App\\Models\\Department";


    /**
     * @param array $filters unit_id Birim id numarası belirtilirse sadece o birime ait bölümler listelenir
     * @return array
     * @throws Exception
     */
    public function getDepartmentsList(array $filters = []): array
    {
        return (new DepartmentRepository())->findBy($filters);
    }

    /**
     * AjaxRouter için bölüm listesi döner
     */
    public function getDepartmentsListResponse(int $unit_id): array
    {
        $departments = $this->getDepartmentsList(['unit_id' => $unit_id, 'active' => true]);
        return [
            'status' => "success",
            'departments' => $departments
        ];
    }

    /**
     * Yeni bölüm oluşturur (POST /ajax/department/add rotası için)
     */
    public function store(array $requestData): array
    {            Gate::authorize(PermissionType::CREATE->value, Department::class, "Yeni bölüm oluşturma yetkiniz yok");

            $dto = (new DepartmentValidator())->getDTO($requestData);
            (new DepartmentService())->saveNew($dto);

            return [
                "status" => "success",
                "msg" => "Bölüm başarıyla oluşturuldu."
            ];
    }

    /**
     * Mevcut bölümü günceller (POST /ajax/department/update rotası için)
     */
    public function update(array $requestData): array
    {            $department = clone (new Department())->find($requestData['id']);
            if (!$department) {
                throw new Exception("Güncellenecek bölüm bulunamadı.");
            }

            Gate::authorize(PermissionType::UPDATE->value, $department, "Bölüm güncelleme yetkiniz yok");

            $dto = (new DepartmentValidator())->getDTO($requestData);
            
            // DTO'dan Model'e aktar
            $department->fill(array_merge(['id' => $requestData['id']], $dto->toArray()));

            (new DepartmentService())->updateDepartment($department);

            return [
                "status" => "success",
                "msg" => "Bölüm başarıyla güncellendi."
            ];
    }

    /**
     * Bölümü siler (POST /ajax/department/delete rotası için)
     */
    public function destroy(array $requestData): array
    {            if (empty($requestData['id'])) {
                throw new Exception("Silinecek bölüm ID'si belirtilmedi.");
            }

            $department = clone (new Department())->find($requestData['id']);
            if (!$department) {
                throw new Exception("Silinecek bölüm bulunamadı.");
            }

            Gate::authorize(PermissionType::DELETE->value, $department, "Bölüm silme yetkiniz yok");

            (new DepartmentService())->deleteDepartment($department);

            return [
                "status" => "success",
                "msg" => "Bölüm başarıyla silindi."
            ];
    }
}