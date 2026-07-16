<?php

namespace App\Repositories;

use App\Models\Unit;
use Exception;

class UnitRepository extends BaseRepository
{
    protected string $modelClass = Unit::class;

    /**
     * Ada göre birim bulur.
     *
     * @param string $name
     * @return Unit|null
     * @throws Exception
     */
    public function findByName(string $name): ?Unit
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Sadece aktif birimleri getirir.
     *
     * @return Unit[]
     * @throws Exception
     */
    public function getActiveUnits(): array
    {
        /** @var Unit $model */
        $model = new $this->modelClass;
        return $model->get()->where(['active' => true])->all();
    }

    /**
     * Birim detay sayfası için birimi bölümleriyle birlikte getirir.
     *
     * @param int $id
     * @return Unit|null
     * @throws Exception
     */
    public function findUnitWithDetails(int $id): ?Unit
    {
        /** @var Unit $model */
        $model = new $this->modelClass;
        return $model->get()
            ->where(['id' => $id])
            ->with(['departments' => ['with' => ['chairperson', 'programs']]])
            ->first();
    }

    /**
     * Tüm birimleri listeler.
     *
     * @return Unit[]
     * @throws Exception
     */
    public function getAllUnits(): array
    {
        /** @var Unit $model */
        $model = new $this->modelClass;
        return $model->get()->all();
    }
}
