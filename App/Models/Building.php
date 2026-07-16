<?php

namespace App\Models;

use App\Core\Model;
use Exception;

class Building extends Model
{
    public ?int $id = null;
    public ?string $name = null;

    public array $classrooms = [];
    protected array $excludeFromDb = ['classrooms'];
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
}
