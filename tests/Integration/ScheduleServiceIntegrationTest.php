<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\Schedule\LessonScheduleService;
use App\DTOs\ScheduleItemDTO;

class ScheduleServiceIntegrationTest extends BaseTestCase
{
    private LessonScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LessonScheduleService();
    }

    /**
     * @test
     */
    public function it_can_save_a_basic_schedule_item()
    {
        $rand = rand(1000, 9999);
        // 1. Gerekli verileri hazırla
        $deptId = $this->insert('departments', ['name' => 'Test Dept ' . $rand]);
        $progId = $this->insert('programs', ['name' => 'Test Prog ' . $rand, 'department_id' => $deptId]);
        $userId = $this->insert('users', ['mail' => "test{$rand}@test.com", 'name' => 'Test', 'last_name' => 'User']);
        $lessonId = $this->insert('lessons', [
            'code' => 'T101' . $rand, 
            'name' => 'Test Lesson', 
            'program_id' => $progId, 
            'department_id' => $deptId,
            'hours' => 2,
            'semester_no' => 1
        ]);
        $scheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => $progId,
            'semester' => 'Güz',
            'academic_year' => '2025-2026',
            'semester_no' => 1
        ]);

        // 2. Servisi çağır (LessonScheduleService::saveScheduleItems array of ScheduleItemDTO bekliyor)
        $dto = ScheduleItemDTO::fromArray([
            'schedule_id' => $scheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '08:00',
            'end_time' => '09:50',
            'status' => 'single',
            'data' => [['lesson_id' => $lessonId, 'lecturer_id' => $userId, 'classroom_id' => null]]
        ]);

        $result = $this->service->saveScheduleItems([$dto]);

        // 3. Doğrula
        $this->assertTrue($result->success);
        
        $stmt = $this->getDb()->prepare("SELECT * FROM schedule_items WHERE schedule_id = ?");
        $stmt->execute([$scheduleId]);
        $savedItems = $stmt->fetchAll();

        $this->assertCount(1, $savedItems);
        $this->assertEquals('08:00:00', $savedItems[0]['start_time']);
        $this->assertEquals('09:50:00', $savedItems[0]['end_time']);
    }

    /**
     * @test
     */
    public function it_wipes_resource_schedules_and_items_completely()
    {
        $rand = rand(1000, 9999);
        $deptId = $this->insert('departments', ['name' => 'Wipe Dept ' . $rand]);
        $progId = $this->insert('programs', ['name' => 'Wipe Prog ' . rand(1000, 9999), 'department_id' => $deptId]);
        $lessonId = $this->insert('lessons', [
            'code' => 'WIP' . $rand,
            'name' => 'Wipe Lesson',
            'program_id' => $progId,
            'department_id' => $deptId,
            'hours' => 2,
            'semester_no' => 1
        ]);
        $scheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'lesson',
            'owner_id' => $lessonId,
            'semester' => 'Güz',
            'academic_year' => '2025-2026'
        ]);
        $this->insert('schedule_items', [
            'schedule_id' => $scheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => 'single',
            'data' => serialize([['lesson_id' => $lessonId]])
        ]);

        // wipeResourceSchedules çağır
        $this->service->wipeResourceSchedules('lesson', $lessonId);

        // Hem schedule hem de schedule_items silinmiş olmalı
        $stmt = $this->getDb()->prepare("SELECT * FROM schedules WHERE owner_type = 'lesson' AND owner_id = ?");
        $stmt->execute([$lessonId]);
        $this->assertEmpty($stmt->fetchAll());

        $stmtItems = $this->getDb()->prepare("SELECT * FROM schedule_items WHERE schedule_id = ?");
        $stmtItems->execute([$scheduleId]);
        $this->assertEmpty($stmtItems->fetchAll());
    }
}
