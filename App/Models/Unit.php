<?php

namespace App\Models;

use App\Core\Model;
use App\Enums\UnitType;
use Exception;

class Unit extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $type = null;
    public ?bool $active = null;

    public ?User $manager = null;
    public array $departments = [];
    public array $buildings = [];
    protected array $excludeFromDb = ['manager', 'departments', 'buildings'];
    protected string $table_name = 'units';

    public function getLabel(): string
    {
        return 'birim';
    }

    public function getLogDetail(): string
    {
        return $this->name ?? 'ID: ' . $this->id;
    }

    public function getTypeName(): string
    {
        return UnitType::tryFrom((string)$this->type)?->getLabel() ?? '';
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getDepartmentsRelation(array $results, array $options = []): array
    {
        $unitIds = array_column($results, 'id');
        if (empty($unitIds))
            return $results;

        $query = (new Department())->get()->where(['unit_id' => ['in' => $unitIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $departments = $query->all();
        $departmentsGrouped = [];
        foreach ($departments as $dept) {
            $departmentsGrouped[$dept->unit_id][] = $dept;
        }

        foreach ($results as &$row) {
            $row['departments'] = $departmentsGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getBuildingsRelation(array $results, array $options = []): array
    {
        $unitIds = array_column($results, 'id');
        if (empty($unitIds)) {
            return $results;
        }

        $query = (new Building())->get()->where(['unit_id' => ['in' => $unitIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $buildings = $query->all();
        $buildingsGrouped = [];
        foreach ($buildings as $b) {
            $buildingsGrouped[$b->unit_id][] = $b;
        }

        foreach ($results as &$row) {
            $row['buildings'] = $buildingsGrouped[$row['id']] ?? [];
        }
        return $results;
    }
}
