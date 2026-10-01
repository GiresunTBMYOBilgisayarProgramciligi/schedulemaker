<?php

namespace App\Repositories;

use App\Models\Building;
use App\Models\User;
use App\Core\Gate;
use App\Enums\UserRole;
use App\Middlewares\AuthMiddleware;
use Exception;

class BuildingRepository extends BaseRepository
{
    protected string $modelClass = Building::class;

    /**
     * Yetkili binaları birim ilişkisiyle birlikte getirir.
     *
     * @param User|null $user
     * @param array $conditions
     * @param array $with
     * @return Building[]
     * @throws Exception
     */
    public function getAuthorizedBuildingsWithHierarchy(?User $user = null, array $conditions = [], array $with = []): array
    {
        $user = $user ?? AuthMiddleware::user();
        $relations = array_merge(['unit'], $with);

        if (!$user) {
            /** @var Building $model */
            $model = new $this->modelClass;
            return $model->get()->where($conditions)->with($relations)->all();
        }

        if (Gate::hasRole($user, UserRole::SubManager)) {
            /** @var Building $model */
            $model = new $this->modelClass;
            return $model->get()->where($conditions)->with($relations)->all();
        }

        $buildings = $this->getAuthorized('view', $conditions, $relations);
        if (empty($buildings)) {
            /** @var Building $model */
            $model = new $this->modelClass;
            $query = $model->get();
            if (!empty($user->unit_id)) {
                $query->where(array_merge(['unit_id' => $user->unit_id], $conditions));
            } else {
                $query->where($conditions);
            }
            $buildings = $query->with($relations)->all();
        }

        return $buildings;
    }

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


}
