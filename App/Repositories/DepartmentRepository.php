<?php

namespace App\Repositories;

use App\Models\Department;
use Exception;

class DepartmentRepository extends BaseRepository
{
    protected string $modelClass = Department::class;

    /**
     * Ada göre bölüm bulur.
     * 
     * @param string $name
     * @return Department|null
     * @throws Exception
     */
    public function findByName(string $name): ?Department
    {
        return $this->findOneBy(["name" => $name]);
    }

    /**
     * Ekleme ve düzenleme sayfalarında oluşturulacak program listesini oluşturur.
     * Bölümü tanımlanmamış bir durum ise sadece program seçiniz verisi olur.
     * Eğer bölümü varsa sadece o programa ait liste gözükür
     * @param int|null $department_id
     * @return object[]
     * @throws Exception
     */
    public function getDepartmentProgramsList(?int $department_id): array
    {
        if (is_null($department_id)) {
            $list = [(object) ["id" => 0, "name" => "Program Seçiniz"]];
        } else {
            $list = (new \App\Controllers\ProgramController())->getProgramsList(['department_id' => $department_id]);
            array_unshift($list, (object) ["id" => 0, "name" => "Program Seçiniz"]);
        }
        return $list;
    }
}
