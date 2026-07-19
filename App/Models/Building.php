<?php

namespace App\Models;

use App\Core\Model;
use Exception;

class Building extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?int $unit_id = null;

    public ?Unit $unit = null;
    public array $classrooms = [];
    protected array $excludeFromDb = ['classrooms', 'unit'];
    protected string $table_name = 'buildings';

    public function getLabel(): string
    {
        return 'bina';
    }

    public function getLogDetail(): string
    {
        return $this->name ?? 'ID: ' . $this->id;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getClassroomsRelation(array $results, array $options = []): array
    {
        $buildingIds = array_column($results, 'id');
        if (empty($buildingIds))
            return $results;

        $query = (new Classroom())->get()->where(['building_id' => ['in' => $buildingIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $classrooms = $query->all();
        $classroomsGrouped = [];
        foreach ($classrooms as $classroom) {
            $classroomsGrouped[$classroom->building_id][] = $classroom;
        }

        foreach ($results as &$row) {
            $row['classrooms'] = $classroomsGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getUnitRelation(array $results, array $options = []): array
    {
        $unitIds = array_filter(array_column($results, 'unit_id'));
        if (empty($unitIds)) {
            return $results;
        }

        $units = (new Unit())->get()->where(['id' => ['in' => $unitIds]])->all();
        $unitsById = [];
        foreach ($units as $unit) {
            $unitsById[$unit->id] = $unit;
        }

        foreach ($results as &$row) {
            $row['unit'] = $unitsById[$row['unit_id']] ?? null;
        }
        return $results;
    }
}
