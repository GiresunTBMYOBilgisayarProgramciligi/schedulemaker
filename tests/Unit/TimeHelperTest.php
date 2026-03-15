<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Helpers\TimeHelper;

class TimeHelperTest extends TestCase
{
    /**
     * @test
     */
    public function it_calculates_duration_minutes_correctly()
    {
        $this->assertEquals(60, TimeHelper::getDurationMinutes('08:00', '09:00'));
        $this->assertEquals(90, TimeHelper::getDurationMinutes('08:00', '09:30'));
        $this->assertEquals(0, TimeHelper::getDurationMinutes('09:00', '08:00'));
        $this->assertEquals(110, TimeHelper::getDurationMinutes('10:00', '11:50'));
    }

    /**
     * @test
     */
    public function it_calculates_end_time_by_hours_correctly()
    {
        $this->assertEquals('09:30', TimeHelper::calculateEndTimeByHours('08:00', 1.5));
        $this->assertEquals('10:00', TimeHelper::calculateEndTimeByHours('08:00', 2));
        $this->assertEquals('08:45', TimeHelper::calculateEndTimeByHours('08:00', 0.75));
    }

    /**
     * @test
     */
    public function it_calculates_item_slots_correctly()
    {
        // 50dk ders + 10dk teneffüs = 60dk slot
        $this->assertEquals(1, TimeHelper::calculateItemSlots('08:00', '08:50', 60));
        $this->assertEquals(2, TimeHelper::calculateItemSlots('08:00', '09:50', 60));
        
        // Sınavlar için 30dk slot (ara yok)
        $this->assertEquals(1, TimeHelper::calculateItemSlots('08:00', '08:30', 30));
        $this->assertEquals(2, TimeHelper::calculateItemSlots('08:00', '09:00', 30));
    }

    /**
     * @test
     */
    public function it_checks_time_overlap_correctly()
    {
        // Çakışan durumlar
        $this->assertTrue(TimeHelper::isOverlapping('08:00', '10:00', '09:00', '11:00'));
        $this->assertTrue(TimeHelper::isOverlapping('09:00', '11:00', '08:00', '10:00'));
        $this->assertTrue(TimeHelper::isOverlapping('08:00', '10:00', '08:30', '09:30')); // Tam kapsama
        
        // Çakışmayan durumlar (bitişik olma durumu çakışma sayılmaz)
        $this->assertFalse(TimeHelper::isOverlapping('08:00', '09:00', '09:00', '10:00'));
        $this->assertFalse(TimeHelper::isOverlapping('09:00', '10:00', '08:00', '09:00'));
        $this->assertFalse(TimeHelper::isOverlapping('08:00', '09:00', '10:00', '11:00'));
    }

    /**
     * @test
     */
    public function it_calculates_end_time_by_slots_correctly()
    {
        // 1 slot (50dk) -> 08:00 - 08:50 (duration: 50)
        $this->assertEquals('08:50', TimeHelper::calculateEndTimeBySlots('08:00', 1, 60, 'lesson', 50));
        
        // 2 slot (50+10+50) -> 08:00 - 09:50 (duration: 50)
        $this->assertEquals('09:50', TimeHelper::calculateEndTimeBySlots('08:00', 2, 60, 'lesson', 50));
        
        // Sınav: 2 slot (30+30) -> 08:00 - 09:00 (duration: 30)
        $this->assertEquals('09:00', TimeHelper::calculateEndTimeBySlots('08:00', 2, 30, 'exam', 30));
    }
}
