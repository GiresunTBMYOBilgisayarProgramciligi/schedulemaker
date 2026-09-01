<?php

namespace Tests\Unit;

use App\Models\Schedule;
use App\Repositories\ScheduleRepository;
use App\Services\Schedule\ScheduleService;
use Tests\BaseTestCase;

class ScheduleTouchTest extends BaseTestCase
{
    public function testTouchWithEmptyInputReturnsZero(): void
    {
        $repo = new ScheduleRepository();
        $this->assertSame(0, $repo->touch([]));
        $this->assertSame(0, $repo->touch(0));
    }

    public function testTouchUpdatesSingleSchedule(): void
    {
        $repo = new ScheduleRepository();
        
        $schedule = new Schedule();
        $schedule->type = 'lesson';
        $schedule->owner_type = 'user';
        $schedule->owner_id = 99991;
        $schedule->semester = 'Güz';
        $schedule->academic_year = '2025-2026';
        $schedule->create();

        $this->assertNotNull($schedule->id);

        $customTime = '2026-05-10 14:30:00';
        $updatedRows = $repo->touch($schedule->id, $customTime);

        $this->assertSame(1, $updatedRows);

        $reloaded = (new Schedule())->find($schedule->id);
        $this->assertSame($customTime, $reloaded->updated_at);
    }

    public function testTouchUpdatesMultipleSchedules(): void
    {
        $repo = new ScheduleRepository();

        $schedule1 = new Schedule();
        $schedule1->type = 'lesson';
        $schedule1->owner_type = 'user';
        $schedule1->owner_id = 99992;
        $schedule1->semester = 'Güz';
        $schedule1->academic_year = '2025-2026';
        $schedule1->create();

        $schedule2 = new Schedule();
        $schedule2->type = 'lesson';
        $schedule2->owner_type = 'classroom';
        $schedule2->owner_id = 99993;
        $schedule2->semester = 'Güz';
        $schedule2->academic_year = '2025-2026';
        $schedule2->create();

        $customTime = '2026-06-01 10:00:00';
        $updatedRows = $repo->touch([$schedule1->id, $schedule2->id, $schedule1->id], $customTime);

        $this->assertSame(2, $updatedRows);

        $reloaded1 = (new Schedule())->find($schedule1->id);
        $reloaded2 = (new Schedule())->find($schedule2->id);

        $this->assertSame($customTime, $reloaded1->updated_at);
        $this->assertSame($customTime, $reloaded2->updated_at);
    }

    public function testScheduleServiceTouchSchedulesDelegation(): void
    {
        $service = new ScheduleService();

        $schedule = new Schedule();
        $schedule->type = 'lesson';
        $schedule->owner_type = 'user';
        $schedule->owner_id = 99995;
        $schedule->semester = 'Güz';
        $schedule->academic_year = '2025-2026';
        $schedule->create();

        $customTime = '2026-08-01 09:15:00';
        $count = $service->touchSchedules([$schedule->id], $customTime);

        $this->assertSame(1, $count);

        $reloaded = (new Schedule())->find($schedule->id);
        $this->assertSame($customTime, $reloaded->updated_at);
    }
}
