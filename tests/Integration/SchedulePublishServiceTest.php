<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\Schedule\SchedulePublishService;
use App\Models\Schedule;

class SchedulePublishServiceTest extends BaseTestCase
{
    private SchedulePublishService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SchedulePublishService();
    }

    public function testTogglePublish(): void
    {
        $scheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => rand(1000, 9999),
            'semester_no' => 1,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);

        $res1 = $this->service->togglePublish($scheduleId);
        $this->assertEquals('success', $res1['status']);
        $this->assertTrue($res1['is_published']);

        $schedule = (new Schedule())->find($scheduleId);
        $this->assertTrue((bool)$schedule->is_published);
        $this->assertNotNull($schedule->published_at);

        // Tekrar toggle yapıldığında yayından kaldırılmalı
        $res2 = $this->service->togglePublish($scheduleId);
        $this->assertEquals('success', $res2['status']);
        $this->assertFalse($res2['is_published']);

        $scheduleUpdated = (new Schedule())->find($scheduleId);
        $this->assertFalse((bool)$scheduleUpdated->is_published);
        $this->assertNull($scheduleUpdated->published_at);
    }

    public function testBulkPublishByUserSingleAndClassroomSingleScope(): void
    {
        $adminId = $this->insert('users', [
            'name' => 'Admin',
            'last_name' => 'User',
            'mail' => 'admin_' . uniqid() . '@example.com',
            'role' => 'admin'
        ]);

        $sessionKey = $_ENV["SESSION_KEY"] ?? 'user_id';
        $_SESSION[$sessionKey] = $adminId;

        $userId = $this->insert('users', [
            'name' => 'Tekil',
            'last_name' => 'Hoca',
            'mail' => 'tekilhoca_' . uniqid() . '@example.com',
            'role' => 'lecturer'
        ]);

        $userScheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'user',
            'owner_id' => $userId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);

        $this->insert('schedule_items', [
            'schedule_id' => $userScheduleId,
            'day_index' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'single',
            'data' => json_encode([['lesson_id' => 1]])
        ]);

        $unitId = $this->insert('units', [
            'name' => 'Mühendislik Fakültesi ' . rand(1000, 9999),
            'type' => 'faculty',
            'active' => 1
        ]);

        $buildingId = $this->insert('buildings', [
            'name' => 'A Blok',
            'unit_id' => $unitId
        ]);

        $classroomId = $this->insert('classrooms', [
            'name' => 'Derslik 101',
            'building_id' => $buildingId,
            'class_size' => 40
        ]);

        $classroomScheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'classroom',
            'owner_id' => $classroomId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);

        $this->insert('schedule_items', [
            'schedule_id' => $classroomScheduleId,
            'day_index' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'single',
            'data' => json_encode([['lesson_id' => 1]])
        ]);

        // 1. user_single scope ile yayınla
        $resUser = $this->service->bulkPublishByScope(
            scope: 'user_single',
            scopeId: $userId,
            semester: 'Güz',
            academicYear: '2025 - 2026',
            type: 'lesson',
            publishStatus: true
        );
        $this->assertEquals(1, $resUser['count']);

        $userSched = (new Schedule())->find($userScheduleId);
        $this->assertTrue((bool)$userSched->is_published);

        // 2. classroom_single scope ile yayınla
        $resClass = $this->service->bulkPublishByScope(
            scope: 'classroom_single',
            scopeId: $classroomId,
            semester: 'Güz',
            academicYear: '2025 - 2026',
            type: 'lesson',
            publishStatus: true
        );
        $this->assertEquals(1, $resClass['count']);

        $classSched = (new Schedule())->find($classroomScheduleId);
        $this->assertTrue((bool)$classSched->is_published);

        // 3. stats testi
        $statsUser = $this->service->getPublishStatsByScope(
            scope: 'user_single',
            scopeId: $userId,
            semester: 'Güz',
            academicYear: '2025 - 2026',
            type: 'lesson'
        );
        $this->assertEquals(1, $statsUser['total_count']);
        $this->assertEquals(1, $statsUser['published_count']);
        $this->assertTrue($statsUser['all_published']);
    }
}

