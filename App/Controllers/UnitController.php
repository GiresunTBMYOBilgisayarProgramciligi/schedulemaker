<?php

namespace App\Controllers;

use App\Enums\PermissionType;

use App\Core\Controller;
use App\Core\Gate;
use App\DTOs\UnitDTO;
use App\Enums\UnitType;
use App\Models\Unit;
use App\Repositories\UnitRepository;
use App\Services\UnitService;
use App\Validators\UnitValidator;
use Exception;

class UnitController extends Controller
{
    protected string $table_name  = 'units';
    protected string $modelName   = Unit::class;

    /**
     * Birim listesi için select-uyumlu liste döndürür.
     *
     * @param array $params
     * @return Unit[]
     * @throws Exception
     */
    public function getUnitsList(array $params = []): array
    {
        return (new UnitRepository())->getAllUnits();
    }

    /**
     * Yeni birim oluşturur (POST /ajax/unit/add)
     */
    public function store(array $requestData): array
    {
        Gate::authorize(PermissionType::CREATE->value, Unit::class, 'Yeni birim oluşturma yetkiniz yok');

        $dto = (new UnitValidator())->getDTO($requestData);
        (new UnitService())->saveNew($dto);

        return [
            'status' => 'success',
            'msg'    => 'Birim başarıyla oluşturuldu.',
        ];
    }

    /**
     * Mevcut birimi günceller (POST /ajax/unit/update)
     */
    public function update(array $requestData): array
    {
        $unit = clone (new Unit())->find($requestData['id']);
        if (!$unit) {
            throw new Exception('Güncellenecek birim bulunamadı.');
        }

        Gate::authorize(PermissionType::UPDATE->value, $unit, 'Birim güncelleme yetkiniz yok');

        $dto = (new UnitValidator())->getDTO($requestData);
        $unit->fill(array_merge(['id' => $requestData['id']], $dto->toArray()));
        (new UnitService())->updateUnit($unit);

        return [
            'status' => 'success',
            'msg'    => 'Birim başarıyla güncellendi.',
        ];
    }

    /**
     * Birimi siler (POST /ajax/unit/delete)
     */
    public function destroy(array $requestData): array
    {
        if (empty($requestData['id'])) {
            throw new Exception("Silinecek birim ID'si belirtilmedi.");
        }

        $unit = clone (new Unit())->find($requestData['id']);
        if (!$unit) {
            throw new Exception('Silinecek birim bulunamadı.');
        }

        Gate::authorize(PermissionType::DELETE->value, $unit, 'Birim silme yetkiniz yok');
        (new UnitService())->deleteUnit($unit);

        return [
            'status' => 'success',
            'msg'    => 'Birim başarıyla silindi.',
        ];
    }
}
