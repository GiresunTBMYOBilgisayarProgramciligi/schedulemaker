<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\Schedule\ConflictService;
use App\DTOs\ConflictFilterDTO;

class ConflictServiceTest extends BaseTestCase
{
    private ConflictService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConflictService();
    }

    public function testCheckScheduleCrashWithNoConflictsReturnsTrue(): void
    {
        $dto = ConflictFilterDTO::fromArray([
            'items' => json_encode([])
        ]);

        $this->assertTrue($this->service->checkScheduleCrash($dto));
    }

    public function testInvalidJsonThrowsException(): void
    {
        $dto = ConflictFilterDTO::fromArray([
            'items' => '{invalid_json'
        ]);

        $this->expectException(\Exception::class);
        $this->service->checkScheduleCrash($dto);
    }

    public function testCheckScheduleCrashDetectsLecturerConflict(): void
    {
        $unitId = $this->insert('units', ['name' => 'Conf Unit ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);
        $deptId = $this->insert('departments', ['name' => 'Conf Dept ' . rand(1000, 9999), 'unit_id' => $unitId, 'active' => 1]);
        $progId = $this->insert('programs', ['name' => 'Conf Prog ' . rand(1000, 9999), 'department_id' => $deptId, 'active' => 1]);
        $buildingId = $this->insert('buildings', ['name' => 'Conf Bldg ' . rand(1000, 9999), 'unit_id' => $unitId]);
        $classroomId = $this->insert('classrooms', [
            'name' => 'Conf Oda ' . rand(100, 999),
            'type' => 1,
            'class_size' => 40,
            'building_id' => $buildingId
        ]);
        $lecturerId = $this->insert('users', [
            'name' => 'Conf',
            'last_name' => 'Hoca',
            'mail' => 'conf_hoca_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $deptId,
            'unit_id' => $unitId
        ]);

        $lesson1Id = $this->insert('lessons', [
            'name' => 'Ders 1',
            'code' => 'CRS1' . rand(100, 999),
            'hours' => 2,
            'department_id' => $deptId,
            'program_id' => $progId,
            'building_id' => $buildingId,
            'classroom_type' => 1,
            'semester_no' => 1,
            'type' => 1
        ]);

        $scheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => $progId,
            'semester_no' => 1,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);

        // Hocanın kendi takvimine çakışan slot ekle (09:00 - 11:00)
        $lecturerScheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'user',
            'owner_id' => $lecturerId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);
        $this->insert('schedule_items', [
            'schedule_id' => $lecturerScheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'single',
            'data' => serialize([['lesson_id' => $lesson1Id, 'lecturer_id' => $lecturerId]])
        ]);

        // Şimdi program takvimine aynı saatte aynı hocayı eklemeye çalış (Çakışmalı)
        $dto = ConflictFilterDTO::fromArray([
            'items' => json_encode([
                [
                    'schedule_id' => $scheduleId,
                    'day_index' => 1,
                    'week_index' => 0,
                    'start_time' => '09:00:00',
                    'end_time' => '11:00:00',
                    'status' => 'single',
                    'data' => [
                        [
                            'lesson_id' => $lesson1Id,
                            'lecturer_id' => $lecturerId,
                            'classroom_id' => $classroomId
                        ]
                    ]
                ]
            ])
        ]);

        $this->expectException(\Exception::class);
        $this->service->checkScheduleCrash($dto);
    }
}
