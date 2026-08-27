<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\ClassroomService;
use App\DTOs\ClassroomDTO;
use App\Models\Classroom;
use App\Enums\ClassroomType;

class ClassroomServiceTest extends BaseTestCase
{
    private ClassroomService $service;
    private int $unitId;
    private int $buildingId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClassroomService();
        $this->unitId = $this->insert('units', [
            'name' => 'Derslik Birim ' . rand(1000, 9999),
            'type' => 'myo',
            'active' => 1
        ]);
        $this->buildingId = $this->insert('buildings', [
            'name' => 'Derslik Bina ' . rand(1000, 9999),
            'unit_id' => $this->unitId
        ]);
    }

    public function testSaveNewClassroomAndDuplicateValidation(): void
    {
        $dto = ClassroomDTO::fromArray([
            'name' => 'Lab 201',
            'class_size' => 40,
            'exam_size' => 25,
            'building_id' => $this->buildingId,
            'type' => ClassroomType::COMPUTER_LAB->value
        ]);

        $classroomId = $this->service->saveNew($dto);
        $this->assertGreaterThan(0, $classroomId);

        $classroom = (new Classroom())->find($classroomId);
        $this->assertEquals('Lab 201', $classroom->name);
        $this->assertEquals(40, $classroom->class_size);
        $this->assertEquals(ClassroomType::COMPUTER_LAB->value, $classroom->type);

        // Aynı binada aynı isimle tekrar derslik eklenmesi engellenmeli
        $this->expectException(\Exception::class);
        $this->service->saveNew($dto);
    }

    public function testUpdateClassroom(): void
    {
        $dto = ClassroomDTO::fromArray([
            'name' => 'D-301',
            'class_size' => 50,
            'exam_size' => 30,
            'building_id' => $this->buildingId,
            'type' => ClassroomType::CLASSROOM->value
        ]);
        $classroomId = $this->service->saveNew($dto);

        $classroom = (new Classroom())->find($classroomId);
        $classroom->name = 'D-301 Güncel';
        $classroom->class_size = 55;
        $this->service->updateClassroom($classroom);

        $updated = (new Classroom())->find($classroomId);
        $this->assertEquals('D-301 Güncel', $updated->name);
        $this->assertEquals(55, $updated->class_size);
    }

    public function testDeleteClassroom(): void
    {
        $dto = ClassroomDTO::fromArray([
            'name' => 'Silinecek Sınıf',
            'class_size' => 20,
            'exam_size' => 15,
            'building_id' => $this->buildingId,
            'type' => ClassroomType::CLASSROOM->value
        ]);
        $classroomId = $this->service->saveNew($dto);

        $classroom = (new Classroom())->find($classroomId);
        $this->service->deleteClassroom($classroom);

        $deleted = (new Classroom())->find($classroomId);
        $this->assertNull($deleted);
    }
}
