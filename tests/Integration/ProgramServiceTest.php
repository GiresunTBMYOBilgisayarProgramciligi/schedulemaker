<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\ProgramService;
use App\DTOs\ProgramDTO;
use App\Models\Program;

class ProgramServiceTest extends BaseTestCase
{
    private ProgramService $service;
    private int $unitId;
    private int $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProgramService();
        $this->unitId = $this->insert('units', [
            'name' => 'Prog Unit ' . rand(1000, 9999),
            'type' => 'myo',
            'active' => 1
        ]);
        $this->deptId = $this->insert('departments', [
            'name' => 'Prog Dept ' . rand(1000, 9999),
            'unit_id' => $this->unitId,
            'active' => 1
        ]);
    }

    public function testSaveNewProgram(): void
    {
        $dto = ProgramDTO::fromArray([
            'name' => 'Mekatronik Programı ' . rand(1000, 9999),
            'department_id' => $this->deptId,
            'active' => 1
        ]);

        $progId = $this->service->saveNew($dto);
        $this->assertGreaterThan(0, $progId);

        $prog = (new Program())->find($progId);
        $this->assertEquals($dto->name, $prog->name);
        $this->assertEquals($this->deptId, $prog->department_id);
    }

    public function testUpdateProgram(): void
    {
        $dto = ProgramDTO::fromArray([
            'name' => 'Lojistik ' . rand(1000, 9999),
            'department_id' => $this->deptId,
            'active' => 1
        ]);
        $progId = $this->service->saveNew($dto);

        $prog = (new Program())->find($progId);
        $prog->name = 'Uluslararası Lojistik';
        $this->service->updateProgram($prog);

        $updated = (new Program())->find($progId);
        $this->assertEquals('Uluslararası Lojistik', $updated->name);
    }

    public function testDeleteProgram(): void
    {
        $dto = ProgramDTO::fromArray([
            'name' => 'Silinecek Program ' . rand(1000, 9999),
            'department_id' => $this->deptId,
            'active' => 1
        ]);
        $progId = $this->service->saveNew($dto);

        $prog = (new Program())->find($progId);
        $this->service->deleteProgram($prog);

        $this->assertNull((new Program())->find($progId));
    }
}
