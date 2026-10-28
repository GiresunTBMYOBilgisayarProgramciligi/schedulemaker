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

    public function testLessonGetGroupLetter(): void
    {
        $lesson = new Lesson();

        $lesson->group_no = 1;
        $this->assertEquals('A', $lesson->getGroupLetter());

        $lesson->group_no = 2;
        $this->assertEquals('B', $lesson->getGroupLetter());

        $lesson->group_no = 3;
        $this->assertEquals('C', $lesson->getGroupLetter());

        $lesson->group_no = 0;
        $this->assertNull($lesson->getGroupLetter());

        $lesson->group_no = null;
        $this->assertNull($lesson->getGroupLetter());
    }

    public function testPrepareScheduleCardHeadersIncludeDayAttributes(): void
    {
        $dto = new \App\DTOs\ScheduleFilterDTO(
            academic_year: '2025 - 2026',
            semester: 'Güz',
            type: 'lesson',
            owner_type: 'program',
            owner_id: 1,
            semester_no: 1
        );

        $html = ScheduleViewHelper::prepareScheduleCard($dto, only_table: true);

        $this->assertStringContainsString('data-day-index="0"', $html);
        $this->assertStringContainsString('data-day-name="Pazartesi"', $html);
        $this->assertStringContainsString('schedule-table', $html);
    }

    public function testPrepareExamScheduleCardHeadersIncludeDayAndDateAttributes(): void
    {
        $dto = new \App\DTOs\ScheduleFilterDTO(
            academic_year: '2025 - 2026',
            semester: 'Güz',
            type: \App\Enums\ExamType::MIDTERM->value,
            owner_type: 'program',
            owner_id: 1,
            semester_no: 1
        );

        $html = ScheduleViewHelper::prepareScheduleCard($dto, only_table: true);

        $this->assertStringContainsString('data-day-index="0"', $html);
        $this->assertStringContainsString('data-day-name="Pazartesi"', $html);
        $this->assertStringContainsString('schedule-table', $html);
    }

    /**
     * @param array<string, int[]> $cells Saat => hücredeki item ID'leri (boş dizi: boş hücre)
     */
    private function makeSlots(array $cells): array
    {
        $slots = [];
        foreach ($cells as $start => $ids) {
            $items = array_map(function (int $id) {
                $item = new ScheduleItem();
                $item->id = $id;
                return $item;
            }, $ids);
            $slots[] = [
                'slotStartTime' => new \DateTime($start),
                'slotEndTime' => (new \DateTime($start))->modify('+50 minutes'),
                'days' => ['day0' => count($items) > 1 ? $items : ($items[0] ?? null)],
            ];
        }
        return $slots;
    }

    public function testCalculateRowSpanMergesConsecutiveSameItems(): void
    {
        $slots = $this->makeSlots(['08:00' => [1], '09:00' => [1], '10:00' => [2], '11:00' => []]);

        $this->assertSame(2, ScheduleViewHelper::calculateRowSpan($slots, 0, 'day0'));
        $this->assertSame(1, ScheduleViewHelper::calculateRowSpan($slots, 2, 'day0'));
        $this->assertSame(1, ScheduleViewHelper::calculateRowSpan($slots, 3, 'day0'), 'Boş hücre birleştirilmez');
    }

    public function testCalculateRowSpanComparesWholeItemSet(): void
    {
        // Sıra farkı önemsiz, eksik item birleştirmeyi keser
        $slots = $this->makeSlots(['08:00' => [1, 2], '09:00' => [2, 1], '10:00' => [1]]);

        $this->assertSame(2, ScheduleViewHelper::calculateRowSpan($slots, 0, 'day0'));
    }

    public function testCalculateRowSpanStopsAtLunchWhenRequested(): void
    {
        $slots = $this->makeSlots(['11:00' => [1], '12:00' => [1], '13:00' => [1]]);

        $this->assertSame(3, ScheduleViewHelper::calculateRowSpan($slots, 0, 'day0'));
        $this->assertSame(1, ScheduleViewHelper::calculateRowSpan($slots, 0, 'day0', stopAtLunch: true));
        $this->assertTrue(ScheduleViewHelper::isLunchSlot($slots[1]));
    }
}
