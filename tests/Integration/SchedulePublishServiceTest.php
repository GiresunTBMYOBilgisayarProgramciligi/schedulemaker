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
}
