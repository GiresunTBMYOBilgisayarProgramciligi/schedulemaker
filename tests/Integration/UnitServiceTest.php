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
        $userId = $this->insert('users', [
            'name'      => 'Prof. Dr. Ahmet',
            'last_name' => 'Yılmaz',
            'mail'      => 'ahmet' . rand(1000, 9999) . '@test.com',
            'password'  => password_hash('123456', PASSWORD_DEFAULT),
            'role'      => 'lecturer',
            'title'     => 'Prof. Dr.'
        ]);

        $dto = UnitDTO::fromArray([
            'name'       => 'Eğitim Fakültesi ' . rand(1000, 9999),
            'type'       => UnitType::Faculty->value,
            'manager_id' => $userId,
            'active'     => 1
        ]);

        $unitId = $this->service->saveNew($dto);
        $this->assertGreaterThan(0, $unitId);

        $unit = (new Unit())->get()->where(['id' => $unitId])->with(['manager'])->first();
        $this->assertEquals($dto->name, $unit->name);
        $this->assertEquals(UnitType::Faculty->value, $unit->type);
        $this->assertEquals($userId, $unit->manager_id);
        $this->assertNotNull($unit->manager);
        $this->assertEquals($userId, $unit->manager->id);
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

    public function testUnitWithSubmanagersRelation(): void
    {
        $dto = UnitDTO::fromArray([
            'name'   => 'Mühendislik Fakültesi ' . rand(1000, 9999),
            'type'   => UnitType::Faculty->value,
            'active' => 1
        ]);
        $unitId = $this->service->saveNew($dto);

        $subManagerId = $this->insert('users', [
            'name'      => 'Doç. Dr. Ayşe',
            'last_name' => 'Kaya',
            'mail'      => 'ayse' . rand(1000, 9999) . '@test.com',
            'password'  => password_hash('123456', PASSWORD_DEFAULT),
            'role'      => 'submanager',
            'unit_id'   => $unitId,
            'title'     => 'Doç. Dr.'
        ]);

        $unit = (new Unit())->get()->where(['id' => $unitId])->with(['submanagers'])->first();
        $this->assertEquals('Dekan', $unit->getManagerTitle());
        $this->assertEquals('Dekan Yardımcısı', $unit->getSubManagerTitle());
        $this->assertNotEmpty($unit->submanagers);
        $this->assertEquals($subManagerId, $unit->submanagers[0]->id);
    }
}
