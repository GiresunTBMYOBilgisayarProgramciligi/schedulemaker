<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\DepartmentService;
use App\DTOs\DepartmentDTO;
use App\Models\Department;
use App\Models\Program;

class DepartmentServiceTest extends BaseTestCase
{
    private DepartmentService $service;
    private int $unitId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DepartmentService();
        $this->unitId = $this->insert('units', [
            'name' => 'Bölüm Test Birimi ' . rand(1000, 9999),
            'type' => 'fakulte',
            'active' => 1
        ]);
    }

    public function testSaveNewDepartment(): void
    {
        $dto = DepartmentDTO::fromArray([
            'name' => 'Yazılım Mühendisliği ' . rand(1000, 9999),
            'unit_id' => $this->unitId,
            'chairperson_id' => null,
            'active' => 1
        ]);

        $deptId = $this->service->saveNew($dto);
        $this->assertGreaterThan(0, $deptId);

        $dept = (new Department())->find($deptId);
        $this->assertEquals($dto->name, $dept->name);
        $this->assertEquals($this->unitId, $dept->unit_id);
    }

    public function testUpdateDepartment(): void
    {
        $dto = DepartmentDTO::fromArray([
            'name' => 'Makine Mühendisliği ' . rand(1000, 9999),
            'unit_id' => $this->unitId,
            'chairperson_id' => null,
            'active' => 1
        ]);
        $deptId = $this->service->saveNew($dto);

        $dept = (new Department())->find($deptId);
        $dept->name = 'Mekatronik Mühendisliği';
        $this->service->updateDepartment($dept);

        $updated = (new Department())->find($deptId);
        $this->assertEquals('Mekatronik Mühendisliği', $updated->name);
    }

    public function testDeleteDepartmentCascadesOrCleans(): void
    {
        $dto = DepartmentDTO::fromArray([
            'name' => 'Silinecek Bölüm ' . rand(1000, 9999),
            'unit_id' => $this->unitId,
            'chairperson_id' => null,
            'active' => 1
        ]);
        $deptId = $this->service->saveNew($dto);

        $dept = (new Department())->find($deptId);
        $this->service->deleteDepartment($dept);

        $this->assertNull((new Department())->find($deptId));
    }
}
