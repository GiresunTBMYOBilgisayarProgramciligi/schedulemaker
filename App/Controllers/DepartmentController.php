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
    protected string $modelName = "App\Models\Department";

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
        return $this->getListByFilters(["name" => $name])[0] ?? false;
    }

    /**
     * Parametre olarak verilen modeli veri tabanına kaydeder
     * @param array $department_data
     * @return int Veri tabanına eklenen Department id numarası
     * @throws Exception
     */
    public function saveNew(array $department_data): int
    {
        try {
            $new_department = new Department();
            $new_department->fill($department_data);
            $new_department->create();
            return $new_department->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            } else {
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
        try {
            $department = new Department();
            $department->fill($department_data);
            $department->update();
            return $department->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu isimde bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
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
        $department = (new Department())->find($id) ?: throw new Exception("Silinecek Bölüm bulunamadı");
        //todo silinen bölüm ile ilgili diğer silme işlemleri
        $department->delete();
    }
}