<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\UnitService;
use App\DTOs\UnitDTO;
use App\Models\Unit;
use App\Enums\UnitType;

class UnitServiceTest extends BaseTestCase
{
    private UnitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnitService();
    }

    public function testSaveNewUnit(): void
    {
        $dto = UnitDTO::fromArray([
            'name' => 'Eğitim Fakültesi ' . rand(1000, 9999),
            'type' => UnitType::Faculty->value,
            'active' => 1
        ]);

        $unitId = $this->service->saveNew($dto);
        $this->assertGreaterThan(0, $unitId);

        $unit = (new Unit())->find($unitId);
        $this->assertEquals($dto->name, $unit->name);
        $this->assertEquals(UnitType::Faculty->value, $unit->type);
    }

    public function testUpdateUnit(): void
    {
        $dto = UnitDTO::fromArray([
            'name' => 'İktisadi İdari Bilimler ' . rand(1000, 9999),
            'type' => UnitType::Faculty->value,
            'active' => 1
        ]);
        $unitId = $this->service->saveNew($dto);

        $unit = (new Unit())->find($unitId);
        $unit->name = 'İİBF Güncel ' . rand(1000, 9999);
        $this->service->updateUnit($unit);

        $updated = (new Unit())->find($unitId);
        $this->assertEquals($unit->name, $updated->name);
    }

    public function testDeleteUnit(): void
    {
        $dto = UnitDTO::fromArray([
            'name' => 'Silinecek Birim ' . rand(1000, 9999),
            'type' => UnitType::Vocational->value,
            'active' => 1
        ]);
        $unitId = $this->service->saveNew($dto);

        $unit = (new Unit())->find($unitId);
        $this->service->deleteUnit($unit);

        $this->assertNull((new Unit())->find($unitId));
    }
}
