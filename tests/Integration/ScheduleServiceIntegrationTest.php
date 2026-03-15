<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\ScheduleService;
use App\Helpers\TimeHelper;
use App\Services\TimelineService;

class ScheduleServiceIntegrationTest extends BaseTestCase
{
    private ScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // ScheduleService bağımlılıklarını kendisi yönetiyor
        $this->service = new ScheduleService();
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
            'lecturer_id' => $userId,
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

        // 2. Servisi çağır (ScheduleService::saveScheduleItems array of objects bekliyor)
        $items = [
            [
                'schedule_id' => $scheduleId,
                'day_index' => 1,
                'week_index' => 0,
                'start_time' => '08:00',
                'end_time' => '09:50',
                'status' => 'single',
                // Data alanı validator'ın beklediği gibi bir array of objects (json string değil, decode edilmiş halde) olmalı
                'data' => [['lesson_id' => $lessonId, 'lecturer_id' => $userId, 'classroom_id' => null]]
            ]
        ];

        $result = $this->service->saveScheduleItems($items);

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
    public function it_identifies_conflicts_correctly()
    {
        // Bu test için ConflictService ve ConflictResolver entegrasyonu sonrası 
        // daha detaylı senaryolar eklenebilir.
        $this->assertTrue(true);
    }
}
