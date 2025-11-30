<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;
use App\Models\Schedule;
use Exception;
use PDO;
use PDOException;
use function App\Helpers\isAuthorized;

class DepartmentController extends Controller
{
    protected string $table_name = "departments";
    protected string $modelName = "App\\Models\\Department";

    /**
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function getDepartmentsList(array $filters = []): array
    {
        if (!isAuthorized("submanager")) {
            $filters['id'] = (new UserController())->getCurrentUser()->department_id;
        }
        return $this->getListByFilters($filters);
    }

    /**
     * @param string $name
     * @return Department|bool
     * @throws Exception
     */
    public function getDepartmentByName(string $name): Department|bool
    {
        $this->logger()->info('Get department by name', $this->logContext(['name' => $name]));
        $dep = $this->getListByFilters(["name" => $name])[0] ?? false;
        if ($dep) {
            $this->logger()->info('Department found by name', $this->logContext(['name' => $name, 'department_id' => $dep->id]));
        } else {
            $this->logger()->warning('Department not found by name', $this->logContext(['name' => $name]));
        }
        return $dep;
    }

    /**
     * Parametre olarak verilen modeli veri tabanına kaydeder
     * @param array $department_data
     * @return int Veri tabanına eklenen Department id numarası
     * @throws Exception
     */
    public function saveNew(array $department_data): int
    {
        $this->logger()->info('Create department requested', $this->logContext(['payload' => ['name' => $department_data['name'] ?? null, 'chairperson_id' => $department_data['chairperson_id'] ?? null]]));
        try {
            $new_department = new Department();
            $new_department->fill($department_data);
            $new_department->create();
            $this->logger()->info('Department created', $this->logContext(['department_id' => $new_department->id]));
            return $new_department->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $this->logger()->warning('Department create failed: duplicate name', $this->logContext(['name' => $department_data['name'] ?? null]));
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                $this->logger()->error('Department create failed: ' . $e->getMessage(), $this->logContext(['exception_code' => $e->getCode()]));
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
    }

    /**
     * @param array $department_data
     * @return int
     * @throws Exception
     */
    public function updateDepartment(array $department_data): int
    {
        $this->logger()->info('Update department requested', $this->logContext(['department_id' => $department_data['id'] ?? null, 'payload' => ['name' => $department_data['name'] ?? null, 'chairperson_id' => $department_data['chairperson_id'] ?? null]]));
        try {
            $department = new Department();
            $department->fill($department_data);
            $department->update();
            $this->logger()->info('Department updated', $this->logContext(['department_id' => $department->id]));
            return $department->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $this->logger()->warning('Department update failed: duplicate name', $this->logContext(['department_id' => $department_data['id'] ?? null, 'name' => $department_data['name'] ?? null]));
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
                $this->logger()->error('Department update failed: ' . $e->getMessage(), $this->logContext(['department_id' => $department_data['id'] ?? null, 'exception_code' => $e->getCode()]));
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
        $this->logger()->info('Delete department requested', $this->logContext(['department_id' => $id]));
        $department = (new Department())->find($id) ?: throw new Exception("Silinecek Bölüm bulunamadı");
        //todo silinen bölüm ile ilgili diğer silme işlemleri
        $department->delete();
        $this->logger()->info('Department deleted', $this->logContext(['department_id' => $id]));
    }
}