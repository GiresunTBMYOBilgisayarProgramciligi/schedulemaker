<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\BuildingService;
use App\Services\ClassroomService;
use App\DTOs\BuildingDTO;
use App\DTOs\ClassroomDTO;
use Exception;

class BuildingAndClassroomUniquenessTest extends BaseTestCase
{
    private BuildingService $buildingService;
    private ClassroomService $classroomService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildingService = new BuildingService();
        $this->classroomService = new ClassroomService();
    }

    /**
     * @test
     */
    public function it_allows_same_building_name_in_different_units()
    {
        $rand = rand(1000, 9999);
        $unit1 = $this->insert('units', ['name' => 'Birim 1 ' . $rand, 'type' => 'myo']);
        $unit2 = $this->insert('units', ['name' => 'Birim 2 ' . $rand, 'type' => 'myo']);

        $dto1 = BuildingDTO::fromArray([
            'name' => 'A Blok ' . $rand,
            'unit_id' => $unit1
        ]);
        $id1 = $this->buildingService->saveNew($dto1);

        $dto2 = BuildingDTO::fromArray([
            'name' => 'A Blok ' . $rand,
            'unit_id' => $unit2
        ]);
        $id2 = $this->buildingService->saveNew($dto2);

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertNotEquals($id1, $id2);
    }

    /**
     * @test
     */
    public function it_prevents_duplicate_building_name_in_the_same_unit()
    {
        $rand = rand(1000, 9999);
        $unit1 = $this->insert('units', ['name' => 'Birim ' . $rand, 'type' => 'myo']);

        $dto1 = BuildingDTO::fromArray([
            'name' => 'Bina ' . $rand,
            'unit_id' => $unit1
        ]);
        $this->buildingService->saveNew($dto1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Bu birimde bu isimde bir bina zaten kayıtlı. Lütfen farklı bir isim giriniz.");

        $dto2 = BuildingDTO::fromArray([
            'name' => 'Bina ' . $rand,
            'unit_id' => $unit1
        ]);
        $this->buildingService->saveNew($dto2);
    }

    /**
     * @test
     */
    public function it_allows_same_classroom_name_in_different_buildings()
    {
        $rand = rand(1000, 9999);
        $unitId = $this->insert('units', ['name' => 'Birim ' . $rand, 'type' => 'myo']);
        $building1 = $this->insert('buildings', ['name' => 'Bina 1 ' . $rand, 'unit_id' => $unitId]);
        $building2 = $this->insert('buildings', ['name' => 'Bina 2 ' . $rand, 'unit_id' => $unitId]);

        $dto1 = ClassroomDTO::fromArray([
            'name' => 'D101',
            'type' => 1,
            'building_id' => $building1,
            'class_size' => 40,
            'exam_size' => 20
        ]);
        $id1 = $this->classroomService->saveNew($dto1);

        $dto2 = ClassroomDTO::fromArray([
            'name' => 'D101',
            'type' => 1,
            'building_id' => $building2,
            'class_size' => 40,
            'exam_size' => 20
        ]);
        $id2 = $this->classroomService->saveNew($dto2);

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertNotEquals($id1, $id2);
    }

    /**
     * @test
     */
    public function it_prevents_duplicate_classroom_name_in_the_same_building()
    {
        $rand = rand(1000, 9999);
        $unitId = $this->insert('units', ['name' => 'Birim ' . $rand, 'type' => 'myo']);
        $buildingId = $this->insert('buildings', ['name' => 'Bina ' . $rand, 'unit_id' => $unitId]);

        $dto1 = ClassroomDTO::fromArray([
            'name' => 'LAB-1',
            'type' => 2,
            'building_id' => $buildingId,
            'class_size' => 30,
            'exam_size' => 15
        ]);
        $this->classroomService->saveNew($dto1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Bu binada bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");

        $dto2 = ClassroomDTO::fromArray([
            'name' => 'LAB-1',
            'type' => 2,
            'building_id' => $buildingId,
            'class_size' => 30,
            'exam_size' => 15
        ]);
        $this->classroomService->saveNew($dto2);
    }
}
