<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Gate;
use App\DTOs\BuildingDTO;
use App\Models\Building;
use App\Repositories\BuildingRepository;
use App\Services\BuildingService;
use App\Validators\BuildingValidator;
use Exception;

class BuildingController extends Controller
{
    protected string $table_name = 'buildings';
    protected string $modelName  = Building::class;

    /**
     * Tüm binaların listesini döndürür (form select için).
     *
     * @return Building[]
     * @throws Exception
     */
    public function getBuildingsList(): array
    {
        return (new BuildingRepository())->getAllBuildings();
    }

    /**
     * Yeni bina oluşturur (POST /ajax/building/add)
     */
    public function store(array $requestData): array
    {
        Gate::authorize('create', Building::class, 'Yeni bina oluşturma yetkiniz yok');

        $dto = (new BuildingValidator())->getDTO($requestData);
        (new BuildingService())->saveNew($dto);

        return [
            'status' => 'success',
            'msg'    => 'Bina başarıyla oluşturuldu.',
        ];
    }

    /**
     * Mevcut binayı günceller (POST /ajax/building/update)
     */
    public function update(array $requestData): array
    {
        $building = clone (new Building())->find($requestData['id']);
        if (!$building) {
            throw new Exception('Güncellenecek bina bulunamadı.');
        }

        Gate::authorize('update', $building, 'Bina güncelleme yetkiniz yok');

        $dto = (new BuildingValidator())->getDTO($requestData);
        $building->fill(array_merge(['id' => $requestData['id']], $dto->toArray()));
        (new BuildingService())->updateBuilding($building);

        return [
            'status' => 'success',
            'msg'    => 'Bina başarıyla güncellendi.',
        ];
    }

    /**
     * Binayı siler (POST /ajax/building/delete)
     */
    public function destroy(array $requestData): array
    {
        if (empty($requestData['id'])) {
            throw new Exception("Silinecek bina ID'si belirtilmedi.");
        }

        $building = clone (new Building())->find($requestData['id']);
        if (!$building) {
            throw new Exception('Silinecek bina bulunamadı.');
        }

        Gate::authorize('delete', $building, 'Bina silme yetkiniz yok');
        (new BuildingService())->deleteBuilding($building);

        return [
            'status' => 'success',
            'msg'    => 'Bina başarıyla silindi.',
        ];
    }
}
