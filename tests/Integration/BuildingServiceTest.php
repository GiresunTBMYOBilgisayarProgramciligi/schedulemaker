<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\BuildingService;
use App\DTOs\BuildingDTO;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;
use App\Models\Building;
use App\Models\Classroom;
use App\Enums\ClassroomType;

class BuildingServiceTest extends BaseTestCase
{
    private BuildingService $service;
    private int $unitId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BuildingService();
        $this->unitId = $this->insert('units', [
            'name' => 'Bina Test Birimi ' . rand(1000, 9999),
            'type' => 'myo',
            'active' => 1
        ]);
    }

    public function testSaveNewBuildingAndDuplicateThrowsException(): void
    {
        $dto = BuildingDTO::fromArray([
            'name' => 'Mühendislik A Blok',
            'unit_id' => $this->unitId
        ]);

        $buildingId = $this->service->saveNew($dto);
        $this->assertGreaterThan(0, $buildingId);

        $building = (new Building())->find($buildingId);
        $this->assertEquals('Mühendislik A Blok', $building->name);
        $this->assertEquals($this->unitId, $building->unit_id);

        // Aynı birimde aynı isimle bina eklenmeye çalışıldığında exception fırlatmalı
        $this->expectException(\Exception::class);
        $this->service->saveNew($dto);
    }

    public function testUpdateBuilding(): void
    {
        $dto = BuildingDTO::fromArray([
            'name' => 'B Blok',
            'unit_id' => $this->unitId
        ]);
        $buildingId = $this->service->saveNew($dto);

        $building = (new Building())->find($buildingId);
        $building->name = 'B Blok Güncel';
        $this->service->updateBuilding($building);

        $updated = (new Building())->find($buildingId);
        $this->assertEquals('B Blok Güncel', $updated->name);
    }

    public function testDeleteBuildingCleansClassroomBuildingId(): void
    {
        $dto = BuildingDTO::fromArray([
            'name' => 'C Blok',
            'unit_id' => $this->unitId
        ]);
        $buildingId = $this->service->saveNew($dto);

        $classroomId = $this->insert('classrooms', [
            'name' => 'D-101',
            'building_id' => $buildingId,
            'type' => ClassroomType::CLASSROOM->value,
            'class_size' => 30,
            'exam_size' => 20
        ]);

        $building = (new Building())->find($buildingId);
        $this->service->deleteBuilding($building);

        $deletedBuilding = (new Building())->find($buildingId);
        $this->assertNull($deletedBuilding);

        // Bağlı dersliğin building_id'si NULL olmalı
        $classroom = (new Classroom())->find($classroomId);
        $this->assertNotNull($classroom);
        $this->assertNull($classroom->building_id);
    }
}
