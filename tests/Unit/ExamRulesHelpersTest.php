<?php

namespace Tests\Unit;

use App\DTOs\ExamAnalysisFilterDTO;
use App\Enums\ExamType;
use App\Helpers\TimeHelper;
use App\Services\Schedule\ExamRules\ClassOverlap;
use App\Services\Schedule\ExamRules\ExamWarningService;
use PHPUnit\Framework\TestCase;

class ExamRulesHelpersTest extends TestCase
{
    private function exam(int $semesterNo, string $start, string $end, int $root = 0): array
    {
        return ['semester_no' => $semesterNo, 'start' => $start, 'end' => $end, 'root' => $root];
    }

    public function testClassOfDerivesClassFromSemesterNo(): void
    {
        $this->assertSame(1, ClassOverlap::classOf(['semester_no' => 1]));
        $this->assertSame(1, ClassOverlap::classOf(['semester_no' => 2]));
        $this->assertSame(2, ClassOverlap::classOf(['semester_no' => 3]));
        $this->assertSame(3, ClassOverlap::classOf(['semester_no' => 5]));
    }

    public function testDifferentClassesWithOverlappingTimeConflict(): void
    {
        $this->assertTrue(ClassOverlap::conflicts($this->exam(1, '09:00', '10:00'), $this->exam(3, '09:00', '10:00')));
        // Ardışık olmayan sınıflar da çakışır, süre kısmen kesişse bile
        $this->assertTrue(ClassOverlap::conflicts($this->exam(1, '09:00', '10:00'), $this->exam(5, '09:30', '10:30')));
    }

    public function testSameClassBackToBackAndCombinedExamsDoNotConflict(): void
    {
        $this->assertFalse(ClassOverlap::conflicts($this->exam(1, '09:00', '10:00'), $this->exam(2, '09:00', '10:00')), 'aynı sınıf düzeyi');
        $this->assertFalse(ClassOverlap::conflicts($this->exam(1, '09:00', '10:00'), $this->exam(3, '10:00', '11:00')), 'peş peşe sınavlar');
        $this->assertFalse(ClassOverlap::conflicts($this->exam(1, '09:00', '10:00', 7), $this->exam(3, '09:00', '10:00', 7)), 'birleşik sınav');
        // Kök bilinmiyorsa (0) iki sınav aynı sınav sayılmaz
        $this->assertTrue(ClassOverlap::conflicts($this->exam(1, '09:00', '10:00', 0), $this->exam(3, '09:00', '10:00', 0)));
    }

    public function testFlattenMergesGroupedMessagesInOrder(): void
    {
        $this->assertSame([], ExamWarningService::flatten([]));
        $this->assertSame(['a', 'b', 'c'], ExamWarningService::flatten([
            ['title' => 'x', 'messages' => ['a', 'b']],
            ['title' => 'y', 'messages' => ['c']],
        ]));
    }

    public function testDayNameFallsBackToDefault(): void
    {
        $this->assertSame('Pazartesi', TimeHelper::dayName(0));
        $this->assertSame('Pazar', TimeHelper::dayName(6));
        $this->assertSame('Bu gün', TimeHelper::dayName(9));
        $this->assertSame('9', TimeHelper::dayName(9, '9'));
    }

    public function testFilterDtoWithScopeKeepsOtherFilters(): void
    {
        $dto = ExamAnalysisFilterDTO::fromArray(['semester' => 'Güz', 'academic_year' => '2026 - 2027', 'exam_type' => 'final-exam', 'unit_id' => '3', 'department_id' => '7']);
        $scoped = $dto->withScope(null, null);

        $this->assertNull($scoped->unit_id);
        $this->assertNull($scoped->department_id);
        $this->assertSame('Güz', $scoped->semester);
        $this->assertSame(ExamType::FINAL, $scoped->examType());
        $this->assertSame(3, $dto->unit_id, 'asıl DTO değişmez');
    }
}
