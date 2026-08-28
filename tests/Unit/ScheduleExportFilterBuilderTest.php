<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Services\Export\ScheduleExportFilterBuilder;

class ScheduleExportFilterBuilderTest extends BaseTestCase
{
    private int $unitId;
    private int $deptId;
    private int $programId;
    private int $userId;
    private int $classroomId;

    protected function setUp(): void
    {
        parent::setUp();

        // Oturumu sıfırla (misafir kullanıcı)
        unset($_SESSION['user_id']);

        $this->unitId = $this->insert('units', [
            'name' => 'Test Birim',
            'type' => 'myo',
            'active' => 1
        ]);

        $this->deptId = $this->insert('departments', [
            'name' => 'Test Bölüm',
            'unit_id' => $this->unitId,
            'active' => 1
        ]);

        $this->programId = $this->insert('programs', [
            'name' => 'Test Program',
            'department_id' => $this->deptId,
            'active' => 1
        ]);

        $this->userId = $this->insert('users', [
            'name' => 'Ahmet',
            'last_name' => 'Yılmaz',
            'mail' => 'ahmet@example.com',
            'password' => 'secret',
            'role' => 'lecturer',
            'unit_id' => $this->unitId,
            'department_id' => $this->deptId,
            'program_id' => $this->programId
        ]);

        $buildingId = $this->insert('buildings', [
            'name' => 'Ana Bina',
            'unit_id' => $this->unitId
        ]);

        $this->classroomId = $this->insert('classrooms', [
            'name' => 'Derslik 101',
            'building_id' => $buildingId
        ]);
    }

    public function testGuestCanBuildFiltersForProgram(): void
    {
        $builder = new ScheduleExportFilterBuilder();
        $filters = [
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => $this->programId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
        ];

        $result = $builder->build($filters);

        $this->assertNotEmpty($result, 'Misafir kullanıcı program filtrelerini alabilmeli');
        $this->assertCount(2, $result, 'Güz dönemi için tüm dönemler (1 ve 3) getirilmelidir');
        $this->assertStringContainsString('Test Program', $result[0]['title']);
        $this->assertEquals($this->programId, $result[0]['filter']['owner_id']);
        $this->assertEquals('program', $result[0]['filter']['owner_type']);
        $this->assertEquals(1, $result[0]['filter']['semester_no']);
        $this->assertEquals(3, $result[1]['filter']['semester_no']);
    }

    public function testCanBuildFiltersForSpecificSemesterNo(): void
    {
        $builder = new ScheduleExportFilterBuilder();
        $filters = [
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => $this->programId,
            'semester_no' => 1,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
        ];

        $result = $builder->build($filters);

        $this->assertCount(1, $result, 'Sadece belirtilen dönem numarası (1) için filtre üretilmelidir');
        $this->assertEquals(1, $result[0]['filter']['semester_no']);
        $this->assertStringContainsString('Test Program', $result[0]['file_title']);
        $this->assertStringContainsString('1', $result[0]['file_title']);
    }

    public function testGuestCanBuildFiltersForLecturer(): void
    {
        $builder = new ScheduleExportFilterBuilder();
        $filters = [
            'type' => 'lesson',
            'owner_type' => 'user',
            'owner_id' => $this->userId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
        ];

        $result = $builder->build($filters);

        $this->assertNotEmpty($result, 'Misafir kullanıcı hoca filtrelerini alabilmeli');
        $this->assertStringContainsString('Ahmet Yılmaz', $result[0]['title']);
        $this->assertEquals($this->userId, $result[0]['filter']['owner_id']);
        $this->assertEquals('user', $result[0]['filter']['owner_type']);
    }

    public function testGuestCanBuildFiltersForClassroom(): void
    {
        $builder = new ScheduleExportFilterBuilder();
        $filters = [
            'type' => 'lesson',
            'owner_type' => 'classroom',
            'owner_id' => $this->classroomId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
        ];

        $result = $builder->build($filters);

        $this->assertNotEmpty($result, 'Misafir kullanıcı derslik filtrelerini alabilmeli');
        $this->assertStringContainsString('Derslik 101', $result[0]['title']);
        $this->assertEquals($this->classroomId, $result[0]['filter']['owner_id']);
        $this->assertEquals('classroom', $result[0]['filter']['owner_type']);
    }
}
