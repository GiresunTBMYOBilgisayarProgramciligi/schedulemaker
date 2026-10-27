<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Routers\HomeRouter;

class HomeRouterTest extends BaseTestCase
{
    private int $unitId;
    private int $deptId;
    private int $buildingId;
    private int $classroomId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitId = $this->insert('units', [
            'name' => 'Home Test Birim ' . rand(1000, 9999),
            'type' => 'fakulte',
            'active' => 1
        ]);

        $this->deptId = $this->insert('departments', [
            'name' => 'Home Test Bolum ' . rand(1000, 9999),
            'unit_id' => $this->unitId,
            'active' => 1
        ]);

        $this->buildingId = $this->insert('buildings', [
            'name' => 'Home Test Bina ' . rand(1000, 9999),
            'unit_id' => $this->unitId
        ]);

        $this->classroomId = $this->insert('classrooms', [
            'name' => 'D101-' . rand(100, 999),
            'building_id' => $this->buildingId
        ]);

        $this->userId = $this->insert('users', [
            'name' => 'Hoca',
            'last_name' => 'Test',
            'mail' => 'hoca_home_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);
    }

    private function getViewData(HomeRouter $router): array
    {
        $ref = new \ReflectionClass(HomeRouter::class);
        $prop = $ref->getProperty('view_data');
        return $prop->getValue($router);
    }

    public function testIndexActionWithProgramParameters(): void
    {
        $_GET = [
            'unit' => (string)$this->unitId,
            'department' => (string)$this->deptId,
            'semester_no' => '2'
        ];

        $router = new HomeRouter();
        ob_start();
        $router->IndexAction();
        ob_end_clean();

        $data = $this->getViewData($router);

        $this->assertEquals('program', $data['active_tab']);
        $this->assertEquals((string)$this->unitId, $data['selected_unit_id']);
        $this->assertEquals((string)$this->deptId, $data['selected_department_id']);
        $this->assertEquals('2', $data['selected_semester_no']);
    }

    public function testIndexActionWithLecturerParameterResolvesUnitAutomatically(): void
    {
        $_GET = [
            'lecturer' => (string)$this->userId
        ];

        $router = new HomeRouter();
        ob_start();
        $router->IndexAction();
        ob_end_clean();

        $data = $this->getViewData($router);

        $this->assertEquals('lecturer', $data['active_tab']);
        $this->assertEquals((string)$this->userId, $data['selected_lecturer_id']);
        $this->assertEquals((string)$this->unitId, $data['selected_lecturer_unit_id']);
    }

    public function testIndexActionWithClassroomParameterResolvesBuildingAndUnitAutomatically(): void
    {
        $_GET = [
            'classroom' => (string)$this->classroomId
        ];

        $router = new HomeRouter();
        ob_start();
        $router->IndexAction();
        ob_end_clean();

        $data = $this->getViewData($router);

        $this->assertEquals('classroom', $data['active_tab']);
        $this->assertEquals((string)$this->classroomId, $data['selected_classroom_id']);
        $this->assertEquals((string)$this->buildingId, $data['selected_classroom_building_id']);
        $this->assertEquals((string)$this->unitId, $data['selected_classroom_unit_id']);
    }

    public function testIndexActionSupportsScheduleTypeParameter(): void
    {
        $_GET = [
            'type' => 'final'
        ];

        $router = new HomeRouter();
        ob_start();
        $router->IndexAction();
        ob_end_clean();

        $data = $this->getViewData($router);

        $this->assertEquals('final-exam', $data['selected_schedule_type']);
    }

    public function testIndexActionResolvesExamTypeAliasWithProgramParameters(): void
    {
        $_GET = [
            'type' => 'Vize',
            'unit' => (string)$this->unitId,
            'department' => (string)$this->deptId,
        ];

        $router = new HomeRouter();
        ob_start();
        $router->IndexAction();
        ob_end_clean();

        $data = $this->getViewData($router);

        $this->assertEquals('midterm-exam', $data['selected_schedule_type']);
        $this->assertEquals('program', $data['active_tab']);
        $this->assertEquals((string)$this->unitId, $data['selected_unit_id']);
    }

    public function testIndexActionFallsBackToLessonForUnknownScheduleType(): void
    {
        $_GET = [
            'type' => 'bilinmeyen'
        ];

        $router = new HomeRouter();
        ob_start();
        $router->IndexAction();
        ob_end_clean();

        $data = $this->getViewData($router);

        $this->assertEquals('lesson', $data['selected_schedule_type']);
    }
}
