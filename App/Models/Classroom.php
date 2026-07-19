<?php

namespace App\Models;

use App\Core\Model;
use App\Enums\ClassroomType;
use App\Enums\OwnerType;
use Exception;

class Classroom extends Model
{

    public ?int $id = null;
    public ?string $name = null;
    public ?int $class_size = null;
    public ?int $exam_size = null;
    public ?int $building_id = null;
    /*
     * Sınıf Türü
     * 1-> Derslik
     * 2-> Bilgisayar laboratuvarı
     * 3-> Uzaktan Eğitim Sınıfı
     * 4-> Karma (Derslik ve Lab)
     */
    public ?int $type = null;

    public ?Building $building = null;
    public array $schedules = [];
    protected array $excludeFromDb = ['building', 'schedules'];
    protected string $table_name = "classrooms";


    /**
     * Log işlemleri için etiket
     * @return string
     */
    public function getLabel(): string
    {
        return "derslik";
    }

    public function getLogDetail(): string
    {
        return $this->name ?? "ID: " . $this->id;
    }
    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getBuildingRelation(array $results, array $options = []): array
    {
        $buildingIds = array_unique(array_column($results, 'building_id'));
        $buildingIds = array_filter($buildingIds);
        if (empty($buildingIds))
            return $results;

        $query = (new Building())->get()->where(['id' => ['in' => $buildingIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $buildings = $query->all();
        $buildingsKeyed = [];
        foreach ($buildings as $building) {
            $buildingsKeyed[$building->id] = $building;
        }

        foreach ($results as &$row) {
            $row['building'] = isset($row['building_id']) && isset($buildingsKeyed[$row['building_id']])
                ? $buildingsKeyed[$row['building_id']]
                : null;
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getSchedulesRelation(array $results, array $options = []): array
    {
        $ids = array_column($results, 'id');
        if (empty($ids))
            return $results;

        $query = (new Schedule())->get()
            ->where([
                'owner_type' => OwnerType::CLASSROOM->value,
                'owner_id' => ['in' => $ids]
            ]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $schedules = $query->all();

        $schedulesGrouped = [];
        foreach ($schedules as $schedule) {
            $schedulesGrouped[$schedule->owner_id][] = $schedule;
        }

        foreach ($results as &$row) {
            $row['schedules'] = $schedulesGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getTypeName(): string
    {
        return ClassroomType::tryFrom((int)$this->type)?->label() ?? "";
    }

    /**
     * Dersliğin bağlı olduğu binanın birimini (Unit) bulur.
     * @return Unit|null
     * @throws Exception
     */
    public function getUnit(): ?Unit
    {
        $unitId = null;
        if (isset($this->building) && $this->building) {
            $unitId = $this->building->unit_id;
        } elseif ($this->building_id) {
            $building = clone (new Building());
            $building = $building->find($this->building_id);
            $unitId = $building ? $building->unit_id : null;
        }
        
        if ($unitId) {
            return clone (new Unit())->find($unitId);
        }
        
        return null;
    }
}