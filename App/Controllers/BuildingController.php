<?php

namespace App\Controllers;

use App\Enums\PermissionType;

use App\Core\Controller;
use App\Core\Gate;
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
     * AjaxRouter için bina listesi döner (birime göre filtreli, yetki kontrollü).
     */
    public function getBuildingsListResponse(int $unit_id): array
    {
        $action = $_GET['action'] ?? 'view';
        $criteria = $unit_id > 0 ? ['unit_id' => $unit_id] : [];
        if ($action === 'public') {
            $buildings = (new BuildingRepository())->findBy($criteria);
        } else {
            $buildings = (new BuildingRepository())->getAuthorized($action, $criteria);
        }

        return [
            'status'    => 'success',
            'buildings' => $buildings,
        ];
    }

    /**
     * AjaxRouter için tüm binaların listesini döndürür (Birim adıyla birlikte).
     */
    public function getAllBuildingsListResponse(): array
    {
        $buildings = (new Building())->get()->with(['unit'])->all();

        $buildingsList = [];
        foreach ($buildings as $building) {
            $unitName = $building->unit ? $building->unit->name : 'Birim Yok';
            $buildingsList[] = [
                'id'   => $building->id,
                'name' => $building->name . " ($unitName)"
            ];
        }

        return [
            'status'    => 'success',
            'buildings' => $buildingsList
        ];
    }


    /**
     * Yeni bina oluşturur (POST /ajax/building/add)
     */
    public function store(array $requestData): array
    {
        $dto = (new BuildingValidator())->getDTO($requestData);
        $buildingMock = new Building();
        $buildingMock->unit_id = $dto->unit_id;
        
        Gate::authorize(PermissionType::CREATE->value, $buildingMock, 'Yeni bina oluşturma yetkiniz yok');

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

        Gate::authorize(PermissionType::UPDATE->value, $building, 'Bina güncelleme yetkiniz yok');

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

        Gate::authorize(PermissionType::DELETE->value, $building, 'Bina silme yetkiniz yok');
        (new BuildingService())->deleteBuilding($building);

        return [
            'status' => 'success',
            'msg'    => 'Bina başarıyla silindi.',
        ];
    }
}
