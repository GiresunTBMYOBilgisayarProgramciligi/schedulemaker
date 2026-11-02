<?php

namespace Tests\Integration;

use App\DTOs\ScheduleItemDTO;
use App\Enums\ClassroomType;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Services\Schedule\ExamAnalysisService;
use App\Services\Schedule\ExamScheduleService;
use Tests\BaseTestCase;

class ExamAnalysisServiceTest extends BaseTestCase
{
    private const SEMESTER = 'Güz';
    private const YEAR = '2025 - 2026';

    private ExamAnalysisService $analysis;
    private ExamScheduleService $examService;
    private int $deptId;
    private int $buildingId;
    private int $progId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analysis = new ExamAnalysisService();
        $this->examService = new ExamScheduleService();

        $unitId = $this->insert('units', ['name' => 'Analysis Unit ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);
        $this->deptId = $this->insert('departments', ['name' => 'Analysis Dept ' . rand(1000, 9999), 'unit_id' => $unitId, 'active' => 1]);
        $this->buildingId = $this->insert('buildings', ['name' => 'Analysis Bldg ' . rand(1000, 9999), 'unit_id' => $unitId]);
        $this->progId = $this->insertProgram();
    }

    private function insertProgram(): int
    {
        return $this->insert('programs', ['name' => 'Analysis Prog ' . rand(1000, 9999), 'department_id' => $this->deptId, 'active' => 1]);
    }

    private function insertLesson(string $name, int $semesterNo, ?int $programId = null, ?string $code = null, int $groupNo = 0): int
    {
        return $this->insert('lessons', [
            'name' => $name,
            'code' => $code ?? 'ANL' . rand(10000, 99999),
            'group_no' => $groupNo,
            'hours' => 3,
            'department_id' => $this->deptId,
            'program_id' => $programId ?? $this->progId,
            'building_id' => $this->buildingId,
            'classroom_type' => ClassroomType::CLASSROOM->value,
            'semester_no' => $semesterNo,
            'type' => 1,
        ]);
    }

    private function insertSchedule(int $programId, int $semesterNo): int
    {
        return $this->insert('schedules', [
            'type' => ExamType::MIDTERM->value,
            'owner_type' => OwnerType::PROGRAM->value,
            'owner_id' => $programId,
            'semester_no' => $semesterNo,
            'semester' => self::SEMESTER,
            'academic_year' => self::YEAR,
            'is_published' => 0,
        ]);
    }

    /** Dersi, yarıyılının program takvimine kaydeder (sınav programı ekleme akışıyla). */
    private function saveExam(int $lessonId, int $scheduleId, int $day, string $start, string $end): void
    {
        $this->examService->saveExamScheduleItems([ScheduleItemDTO::fromArray([
            'schedule_id' => $scheduleId,
            'day_index' => $day,
            'week_index' => 0,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'single',
            'data' => [['lesson_id' => $lessonId]],
        ])]);
    }

    private function overlaps(?array $programIds = null): array
    {
        return $this->analysis->classOverlaps(ExamType::MIDTERM, self::SEMESTER, self::YEAR, $programIds ?? [$this->progId]);
    }

    private function dailyLimit(?int $max = null): array
    {
        return $this->analysis->dailyLimitViolations(ExamType::MIDTERM, self::SEMESTER, self::YEAR, [$this->progId], $max);
    }

    public function testClassOverlapsFindsDifferentClassLevelsAtSameTime(): void
    {
        $firstClass = $this->insertLesson('Matematik', 1);
        $secondClass = $this->insertLesson('Fizik', 3);
        $firstSchedule = $this->insertSchedule($this->progId, 1);
        $secondSchedule = $this->insertSchedule($this->progId, 3);

        $this->saveExam($firstClass, $firstSchedule, 1, '09:00:00', '10:00:00');
        $this->saveExam($secondClass, $secondSchedule, 1, '09:30:00', '10:30:00');

        $result = $this->overlaps();
        $this->assertSame(1, $result['conflict_count']);
        $this->assertSame(2, $result['item_count']);
        $this->assertCount(1, $result['programs']);
        $this->assertSame($this->progId, $result['programs'][0]['program_id']);

        $conflict = $result['programs'][0]['conflicts'][0];
        $this->assertSame('Salı', $conflict['day']);
        $this->assertSame(1, $conflict['first']['class']);
        $this->assertSame(2, $conflict['second']['class']);
        $this->assertSame($firstClass, $conflict['first']['lesson_id']);
    }

    public function testClassOverlapsIgnoresConsecutiveTimesAndOtherDays(): void
    {
        $first = $this->insertLesson('Matematik', 1);
        $secondClass = $this->insertLesson('Fizik', 3);
        $firstSchedule = $this->insertSchedule($this->progId, 1);
        $secondSchedule = $this->insertSchedule($this->progId, 3);

        $this->saveExam($first, $firstSchedule, 1, '09:00:00', '10:00:00');
        // Peş peşe (kesişmeyen) saat
        $this->saveExam($secondClass, $secondSchedule, 1, '10:00:00', '11:00:00');
        // Başka gün
        $this->saveExam($this->insertLesson('Biyoloji', 3), $secondSchedule, 2, '09:00:00', '10:00:00');

        $this->assertSame(0, $this->overlaps()['conflict_count']);
    }

    public function testClassOverlapsTreatsCombinedExamAsSingleExam(): void
    {
        $parent = $this->insertLesson('Ortak Ders', 1);
        $child = $this->insertLesson('Ortak Ders 2. Sınıf', 3);
        $this->insert('lesson_combinations', [
            'parent_lesson_id' => $parent,
            'child_lesson_id' => $child,
            'type' => 'exam',
            'semester' => self::SEMESTER,
            'academic_year' => self::YEAR,
        ]);
        $firstSchedule = $this->insertSchedule($this->progId, 1);
        $secondSchedule = $this->insertSchedule($this->progId, 3);

        $this->saveExam($parent, $firstSchedule, 1, '09:00:00', '10:00:00');
        $this->saveExam($child, $secondSchedule, 1, '09:00:00', '10:00:00');

        $this->assertSame(0, $this->overlaps()['conflict_count']);
    }

    public function testClassOverlapsRespectsProgramFilter(): void
    {
        $otherProgId = $this->insertProgram();
        $this->saveExam($this->insertLesson('Matematik', 1, $otherProgId), $this->insertSchedule($otherProgId, 1), 1, '09:00:00', '10:00:00');
        $this->saveExam($this->insertLesson('Fizik', 3, $otherProgId), $this->insertSchedule($otherProgId, 3), 1, '09:00:00', '10:00:00');

        $this->assertSame(0, $this->overlaps([$this->progId])['conflict_count']);
        $this->assertSame(1, $this->overlaps([$otherProgId])['conflict_count']);
        $this->assertSame(1, $this->overlaps([$this->progId, $otherProgId])['conflict_count']);
        // Boş program listesi hiçbir programı kapsamaz
        $this->assertSame(0, $this->overlaps([])['item_count']);
        // Aynı donem ve yıl için başka sınav tipinde kayıt yok
        $this->assertSame(0, $this->analysis->classOverlaps(ExamType::FINAL, self::SEMESTER, self::YEAR, [$otherProgId])['item_count']);
    }

    public function testDailyLimitFlagsClassWithMoreThanTwoExamsInOneDay(): void
    {
        $schedule = $this->insertSchedule($this->progId, 1);
        $this->saveExam($this->insertLesson('Matematik', 1), $schedule, 1, '09:00:00', '10:00:00');
        $this->saveExam($this->insertLesson('Fizik', 1), $schedule, 1, '11:00:00', '12:00:00');

        $this->assertSame(0, $this->dailyLimit()['violation_count']);

        $this->saveExam($this->insertLesson('Kimya', 1), $schedule, 1, '14:00:00', '15:00:00');
        // Başka günde tek sınav sınırı etkilemez
        $this->saveExam($this->insertLesson('Biyoloji', 1), $schedule, 2, '14:00:00', '15:00:00');

        $result = $this->dailyLimit();
        $this->assertSame(1, $result['violation_count']);
        $this->assertSame(2, $result['max']);
        $this->assertSame(1, $result['class_count']);

        $violation = $result['programs'][0]['violations'][0];
        $this->assertSame(3, $violation['exam_count']);
        $this->assertSame('Salı', $violation['day']);
        $this->assertSame(1, $violation['class']);
        $this->assertSame('09:00:00', $violation['exams'][0]['start']);
        $this->assertCount(3, $violation['exams']);
    }

    public function testDailyLimitCountsGroupsAsSingleExamAndHonorsCustomMax(): void
    {
        $schedule = $this->insertSchedule($this->progId, 1);
        // Aynı koda sahip gruplar tek sınav olarak aynı saat dilimine kaydedilir
        $groupA = $this->insertLesson('Fizik A', 1, null, 'FIZ101', 1);
        $this->insertLesson('Fizik B', 1, null, 'FIZ101', 2);
        $this->saveExam($groupA, $schedule, 1, '09:00:00', '10:00:00');
        $this->saveExam($this->insertLesson('Kimya', 1), $schedule, 1, '11:00:00', '12:00:00');

        // 2 grup + 1 ders = 2 sınav: varsayılan sınırı aşmaz
        $this->assertSame(0, $this->dailyLimit()['violation_count']);

        // Sınır 1'e düşürülünce aynı gün 2 sınav ihlal olur
        $result = $this->dailyLimit(1);
        $this->assertSame(1, $result['max']);
        $this->assertSame(1, $result['violation_count']);
        $this->assertSame(2, $result['programs'][0]['violations'][0]['exam_count']);
    }

    public function testDailyLimitCountsEachClassSeparately(): void
    {
        $firstSchedule = $this->insertSchedule($this->progId, 1);
        $secondSchedule = $this->insertSchedule($this->progId, 3);
        // 1. sınıfta 2, 2. sınıfta 2 sınav: toplamda 4 ama sınıf başına sınır aşılmıyor
        $this->saveExam($this->insertLesson('A', 1), $firstSchedule, 1, '09:00:00', '10:00:00');
        $this->saveExam($this->insertLesson('B', 1), $firstSchedule, 1, '11:00:00', '12:00:00');
        $this->saveExam($this->insertLesson('C', 3), $secondSchedule, 1, '13:00:00', '14:00:00');
        $this->saveExam($this->insertLesson('D', 3), $secondSchedule, 1, '15:00:00', '16:00:00');

        $this->assertSame(0, $this->dailyLimit()['violation_count']);
    }
}
