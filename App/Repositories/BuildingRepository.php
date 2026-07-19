<?php

namespace App\Repositories;

use App\Models\Building;
use Exception;

class BuildingRepository extends BaseRepository
{
    protected string $modelClass = Building::class;

    /**
     * Ada göre bina bulur.
     *
     * @param string $name
     * @return Building|null
     * @throws Exception
     */
    public function findByName(string $name): ?Building
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Tüm binaları listeler.
     *
     * @return Building[]
     * @throws Exception
     */
    public function getAllBuildings(): array
    {
        /** @var Building $model */
        $model = new $this->modelClass;
        return $model->get()->with(['unit'])->all();
    }

    /**
     * Bina detay sayfası için binayı derslikleriyle birlikte getirir.
     *
     * @param int $id
     * @return Building|null
     * @throws Exception
     */
    public function findBuildingWithClassrooms(int $id): ?Building
    {
        /** @var Building $model */
        $model = new $this->modelClass;
        return $model->get()
            ->where(['id' => $id])
            ->with(['classrooms', 'unit'])
            ->first();
    }

    /**
     * @param string $action
     * @param array $criteria
     * @return array
     * @throws Exception
     */
    public function getAuthorized(string $action = 'view', array $criteria = []): array
    {
        /** @var Building $model */
        $model = new $this->modelClass;
        $query = $model->get()->with(['unit']);
        if (!empty($criteria)) {
            $query->where($criteria);
        }
        $buildings = $query->all();
        return array_values(array_filter($buildings, fn($b) => \App\Core\Gate::check($action, $b)));
    }
}
