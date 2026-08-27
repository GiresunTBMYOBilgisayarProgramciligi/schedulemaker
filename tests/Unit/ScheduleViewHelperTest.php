<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Helpers\ScheduleViewHelper;
use App\Models\ScheduleItem;
use App\Models\Schedule;
use App\Models\Lesson;

class ScheduleViewHelperTest extends BaseTestCase
{
    public function testBuildLessonCardAttributes(): void
    {
        $scheduleItem = new ScheduleItem();
        $scheduleItem->id = 101;

        $lesson = new Lesson();
        $lesson->id = 55;
        $lesson->name = 'Web Programlama';
        $lesson->code = 'BBP201';
        $lesson->group_no = 1;

        $slotData = (object)[
            'lesson' => $lesson
        ];

        $schedule = new Schedule();
        $schedule->id = 10;
        $schedule->owner_type = 'program';
        $schedule->owner_id = 2;

        $attrs = ScheduleViewHelper::buildLessonCardAttributes(
            $scheduleItem,
            $slotData,
            $schedule,
            true,
            'lesson'
        );

        $this->assertIsArray($attrs);
        $this->assertEquals('true', $attrs['draggable']);
        $this->assertEquals(101, $attrs['data-schedule-item-id']);
        $this->assertEquals(55, $attrs['data-lesson-id']);
        $this->assertEquals(1, $attrs['data-group-no']);
    }
}
