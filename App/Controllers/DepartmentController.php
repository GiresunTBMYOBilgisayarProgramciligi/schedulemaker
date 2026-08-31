<?php

namespace App\Controllers;

use App\Enums\PermissionType;

use App\Core\Controller;
use App\Models\Department;
use App\Repositories\DepartmentRepository;
use Exception;
use App\Core\Gate;
use App\Validators\DepartmentValidator;
use App\Services\DepartmentService;
use function App\Helpers\getMaxSemesterNo;

class DepartmentController extends Controller
{
    protected string $table_name = "departments";
    protected string $modelName = "App\\Models\\Department";


    /**
     * AjaxRouter için bölüm listesi döner (yetki filtrelemesi uygulanır)
     */
    public function getDepartmentsListResponse(int $unit_id): array
    {
        $action = $_GET['action'] ?? 'view';
        if ($action === 'public') {
            $departments = (new DepartmentRepository())->findBy(['unit_id' => $unit_id, 'active' => true]);
        } else {
            $departments = (new DepartmentRepository())->getAuthorized($action, ['unit_id' => $unit_id, 'active' => true]);
        }

        $unitMaxSemester = getMaxSemesterNo(null, null, $unit_id);
        foreach ($departments as $dept) {
            $dept->max_semester = getMaxSemesterNo(null, $dept->id, $unit_id);
        }

        return [
            'status' => "success",
            'unit_max_semester' => $unitMaxSemester,
            'departments' => $departments
        ];
    }

    /**
     * Yeni bölüm oluşturur (POST /ajax/department/add rotası için)
     */
    public function store(array $requestData): array
    {
        $dto = (new DepartmentValidator())->getDTO($requestData);
        Gate::authorize(PermissionType::CREATE->value, Department::class, "Yeni bölüm oluşturma yetkiniz yok", $dto);

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
    {
        $department = clone (new Department())->find($requestData['id']);
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
    {
        if (empty($requestData['id'])) {
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