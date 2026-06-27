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
}
