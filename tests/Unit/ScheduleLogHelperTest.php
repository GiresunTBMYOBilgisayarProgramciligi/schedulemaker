<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Helpers\ScheduleLogHelper;
use App\DTOs\ScheduleItemDTO;

class ScheduleLogHelperTest extends TestCase
{
    public function testGetChangeDetailForLesson(): void
    {
        $dto = new ScheduleItemDTO(
            scheduleId: 1,
            dayIndex: 0, // Pazartesi
            weekIndex: 0,
            startTime: '09:00',
            endTime: '11:00',
            status: 'single',
            data: [],
            detail: null
        );

        $detail = ScheduleLogHelper::getChangeDetail('eklendi', $dto, null, false);
        $this->assertStringContainsString('Pazartesi 09:00 - 11:00 saatine eklendi', $detail);
        $this->assertStringContainsString('dersi', $detail);
    }

    public function testGetChangeDetailForMovedLesson(): void
    {
        $oldDto = new ScheduleItemDTO(
            scheduleId: 1,
            dayIndex: 0, // Pazartesi
            weekIndex: 0,
            startTime: '09:00',
            endTime: '11:00',
            status: 'single',
            data: [],
            detail: null
        );

        $newDto = new ScheduleItemDTO(
            scheduleId: 1,
            dayIndex: 2, // Çarşamba
            weekIndex: 0,
            startTime: '13:00',
            endTime: '15:00',
            status: 'single',
            data: [],
            detail: null
        );

        $detail = ScheduleLogHelper::getChangeDetail('taşındı', $newDto, $oldDto, false);
        $this->assertStringContainsString('Pazartesi 09:00 - 11:00 saatinden Çarşamba 13:00 - 15:00 saatine taşındı', $detail);
    }

    public function testExtractLecturerIds(): void
    {
        $dto = new ScheduleItemDTO(
            scheduleId: 1,
            dayIndex: 0,
            weekIndex: 0,
            startTime: '09:00',
            endTime: '11:00',
            status: 'single',
            data: [
                ['lesson_id' => 5, 'lecturer_id' => 12],
                ['lesson_id' => 6, 'lecturer_id' => 15],
            ],
            detail: [
                'assignments' => [
                    ['observer_id' => 18]
                ]
            ]
        );

        $ids = ScheduleLogHelper::extractLecturerIds($dto);
        $this->assertEquals([12, 15, 18], $ids);
    }
}
