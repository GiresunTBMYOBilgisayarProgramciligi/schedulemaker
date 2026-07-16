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
        return $model->get()->all();
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
            ->with(['classrooms'])
            ->first();
    }
}
