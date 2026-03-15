<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\TimelineService;

class TimelineServiceTest extends TestCase
{
    private TimelineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TimelineService();
    }

    /**
     * @test
     */
    public function it_calculates_critical_points_correctly()
    {
        $start = '08:00';
        $end = '11:00';
        $internalPoints = ['09:15', '10:45'];
        $duration = 50;
        $break = 10;

        $points = $this->service->getCriticalPoints($start, $end, $internalPoints, $duration, $break);

        // Beklenen noktalar: 
        // 08:00 (start)
        // 08:50 (1. ders bitiş)
        // 09:00 (1. teneffüs bitiş / 2. ders başla)
        // 09:15 (internal)
        // 09:50 (2. ders bitiş)
        // 10:00 (2. teneffüs bitiş / 3. ders başla)
        // 10:45 (internal)
        // 10:50 (3. ders bitiş)
        // 11:00 (end)
        
        $expected = ['08:00', '08:50', '09:00', '09:15', '09:50', '10:00', '10:45', '10:50', '11:00'];
        $this->assertEquals($expected, $points);
    }

    /**
     * @test
     */
    public function it_merges_contiguous_segments_correctly()
    {
        $segments = [
            [
                'start' => '08:00',
                'end' => '08:50',
                'isBreak' => false,
                'shouldKeep' => true,
                'data' => [['lesson_id' => 1]],
                'detail' => ['type' => 'lesson']
            ],
            [
                'start' => '08:50',
                'end' => '09:00',
                'isBreak' => true,
                'shouldKeep' => true, // Teneffüs, iki tarafında aynı ders varsa tutulur
                'data' => [], // İlk aşamada boş
                'detail' => []
            ],
            [
                'start' => '09:00',
                'end' => '09:50',
                'isBreak' => false,
                'shouldKeep' => true,
                'data' => [['lesson_id' => 1]],
                'detail' => ['type' => 'lesson']
            ],
            [
                'start' => '09:50',
                'end' => '10:00',
                'isBreak' => true,
                'shouldKeep' => true,
                'data' => [],
                'detail' => []
            ],
            [
                'start' => '10:00',
                'end' => '10:50',
                'isBreak' => false,
                'shouldKeep' => true,
                'data' => [['lesson_id' => 2]],
                'detail' => ['type' => 'lesson']
            ]
        ];

        $merged = $this->service->mergeContiguousSegments($segments, 10);

        // 1. Birleştirme sonrası:
        // 08:00 - 09:50 (Ders 1 + Teneffüs + Ders 1) -> Teneffüs verisini Ders 1'den alır ve birleşir
        // 10:00 - 10:50 (Ders 2) -> 09:50-10:00 arası teneffüs atılır (shouldKeep false olur çünkü Ders 1 ve Ders 2 farklı)

        $this->assertCount(2, $merged);
        
        $this->assertEquals('08:00', $merged[0]['start']);
        $this->assertEquals('09:50', $merged[0]['end']);
        $this->assertEquals([['lesson_id' => 1]], $merged[0]['data']);

        $this->assertEquals('10:00', $merged[1]['start']);
        $this->assertEquals('10:50', $merged[1]['end']);
        $this->assertEquals([['lesson_id' => 2]], $merged[1]['data']);
    }

    /**
     * @test
     */
    public function it_determines_status_correctly()
    {
        // tercih edilen
        $this->assertEquals('preferred', $this->service->determineStatus([], 'preferred'));
        
        // uygun değil
        $this->assertEquals('unavailable', $this->service->determineStatus([], 'unavailable'));
        
        // normal (boş) -> varsayılan single
        $this->assertEquals('single', $this->service->determineStatus([], 'normal', false));

        // preferred alanı boş kalırsa preferred dönmeli
        $this->assertEquals('preferred', $this->service->determineStatus([], 'normal', true));

        // grup dersi
        $data = [['lesson_id' => 10]];
        $lessonGroups = [10 => 1]; // lesson_id 10 belongs to group 1
        $this->assertEquals('group', $this->service->determineStatus($data, 'normal', false, $lessonGroups));

        // normal ders
        $data = [['lesson_id' => 11]];
        $lessonGroups = [11 => 0]; // group_no 0 means single
        $this->assertEquals('single', $this->service->determineStatus($data, 'normal', false, $lessonGroups));
    }
}
