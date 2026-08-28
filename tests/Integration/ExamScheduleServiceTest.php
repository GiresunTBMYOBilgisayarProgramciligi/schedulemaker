<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\Schedule\ExamScheduleService;
use App\DTOs\ScheduleItemDTO;
use App\Models\ScheduleItem;
use App\Enums\OwnerType;
use App\Enums\ClassroomType;

class ExamScheduleServiceTest extends BaseTestCase
{
    private ExamScheduleService $service;
    private int $unitId;
    private int $deptId;
    private int $progId;
    private int $buildingId;
    private int $classroomId;
    private int $lecturerId;
    private int $lessonId;
    private int $programScheduleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExamScheduleService();

        $this->unitId = $this->insert('units', ['name' => 'Exam Unit ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);
        $this->deptId = $this->insert('departments', ['name' => 'Exam Dept ' . rand(1000, 9999), 'unit_id' => $this->unitId, 'active' => 1]);
        $this->progId = $this->insert('programs', ['name' => 'Exam Prog ' . rand(1000, 9999), 'department_id' => $this->deptId, 'active' => 1]);
        $this->buildingId = $this->insert('buildings', ['name' => 'Exam Bldg ' . rand(1000, 9999), 'unit_id' => $this->unitId]);
        $this->classroomId = $this->insert('classrooms', [
            'name' => 'Exam Class ' . rand(100, 999),
            'type' => ClassroomType::CLASSROOM->value,
            'class_size' => 50,
            'building_id' => $this->buildingId
        ]);
        $this->lecturerId = $this->insert('users', [
            'name' => 'Exam',
            'last_name' => 'Lecturer',
            'mail' => 'exam_lec_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);
        $this->lessonId = $this->insert('lessons', [
            'name' => 'Matematik 1',
            'code' => 'MAT' . rand(100, 999),
            'hours' => 3,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'building_id' => $this->buildingId,
            'classroom_type' => ClassroomType::CLASSROOM->value,
            'semester_no' => 1,
            'type' => 1
        ]);

        $this->programScheduleId = $this->insert('schedules', [
            'type' => 'final-exam',
            'owner_type' => OwnerType::PROGRAM->value,
            'owner_id' => $this->progId,
            'semester_no' => 1,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);
    }

    public function testSaveExamScheduleItems(): void
    {
        $dto = ScheduleItemDTO::fromArray([
            'schedule_id' => $this->programScheduleId,
            'day_index' => 2,
            'week_index' => 0,
            'start_time' => '10:00:00',
            'end_time' => '11:30:00',
            'status' => 'single',
            'data' => [
                [
                    'lesson_id' => $this->lessonId,
                    'assignments' => [
                        [
                            'classroom_id' => $this->classroomId,
                            'lecturer_id' => $this->lecturerId
                        ]
                    ]
                ]
            ]
        ]);

        $created = $this->service->saveExamScheduleItems([$dto]);

        $this->assertNotEmpty($created);

        // Program takviminde item oluşmuş olmalı
        $items = (new ScheduleItem())->get()->where([
            'schedule_id' => $this->programScheduleId,
            'day_index' => 2
        ])->all();

        $this->assertNotEmpty($items);
    }
}
