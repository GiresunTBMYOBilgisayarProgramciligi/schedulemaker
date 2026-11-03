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
    private ?int $lecturerId = null;

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

    private function insertLesson(string $name, int $semesterNo, ?int $programId = null, ?string $code = null, int $groupNo = 0, int $size = 0, int $type = 1): int
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
            'size' => $size,
            'type' => $type,
        ]);
    }

    /** Dersi dönemde bir öğretim elemanına atar (eksik sınav programı analizi yalnızca atanmış dersleri kapsar). */
    private function assign(int $lessonId): void
    {
        $this->lecturerId ??= $this->insert('users', [
            'name' => 'Analiz', 'last_name' => 'Hoca', 'mail' => 'analysis_' . rand(10000, 99999) . '@test.com',
            'role' => 'lecturer', 'department_id' => $this->deptId,
        ]);
        $this->insert('lesson_assignments', ['lesson_id' => $lessonId, 'lecturer_id' => $this->lecturerId, 'semester' => self::SEMESTER, 'academic_year' => self::YEAR]);
    }

    private function insertClassroom(int $examSize): int
    {
        return $this->insert('classrooms', [
            'name' => 'AN' . rand(100, 999), 'type' => ClassroomType::CLASSROOM->value,
            'class_size' => $examSize * 2, 'exam_size' => $examSize, 'building_id' => $this->buildingId,
        ]);
    }

    /** Sınavı, derslik ataması (ve isteğe bağlı "kalan mevcudu yok say" bayrağı) ile kaydeder. */
    private function saveExamInClassroom(int $lessonId, int $scheduleId, int $classroomId, int $day = 1, bool $ignoreRemaining = false): void
    {
        $this->examService->saveExamScheduleItems([ScheduleItemDTO::fromArray([
            'schedule_id' => $scheduleId,
            'day_index' => $day,
            'week_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'single',
            'data' => [['lesson_id' => $lessonId]],
            'detail' => ['assignments' => [['classroom_id' => $classroomId, 'observers' => []]]] + ($ignoreRemaining ? ['ignore_remaining' => true] : []),
        ])]);
    }

    private function incomplete(): array
    {
        return $this->analysis->incompleteLessons(ExamType::MIDTERM, self::SEMESTER, self::YEAR, [$this->progId]);
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

    public function testIncompleteLessonsReportsUnplacedAndPartialLessons(): void
    {
        $schedule = $this->insertSchedule($this->progId, 1);
        $unplaced = $this->insertLesson('Eklenmemiş', 1, null, null, 0, 30);
        $partial = $this->insertLesson('Kapasitesi Yetersiz', 1, null, null, 0, 50);
        $complete = $this->insertLesson('Tamam', 1, null, null, 0, 30);
        foreach ([$unplaced, $partial, $complete] as $lessonId) {
            $this->assign($lessonId);
        }
        $this->saveExamInClassroom($partial, $schedule, $this->insertClassroom(30), 1);
        $this->saveExamInClassroom($complete, $schedule, $this->insertClassroom(30), 2);

        $result = $this->incomplete();
        $this->assertSame(3, $result['lesson_count']);
        $this->assertSame(2, $result['incomplete_count']);
        $this->assertSame(1, $result['unplaced_count']);
        $this->assertSame(1, $result['partial_count']);

        $lessons = array_column($result['programs'][0]['lessons'], null, 'lesson_id');
        $this->assertEquals(['status' => 'unplaced', 'size' => 30, 'placed' => 0, 'remaining' => 30],
            array_intersect_key($lessons[$unplaced], array_flip(['status', 'size', 'placed', 'remaining'])));
        $this->assertEquals(['status' => 'partial', 'size' => 50, 'placed' => 30, 'remaining' => 20],
            array_intersect_key($lessons[$partial], array_flip(['status', 'size', 'placed', 'remaining'])));
        $this->assertArrayNotHasKey($complete, $lessons);
        $this->assertSame(1, $lessons[$unplaced]['class']);
        $this->assertCount(1, $lessons[$unplaced]['lecturers']);
    }

    public function testIncompleteLessonsHonorsIgnoreRemainingFlag(): void
    {
        $schedule = $this->insertSchedule($this->progId, 1);
        $lesson = $this->insertLesson('Küçük Fark', 1, null, null, 0, 36);
        $this->assign($lesson);
        $this->saveExamInClassroom($lesson, $schedule, $this->insertClassroom(35), 1, true);

        $this->assertSame(0, $this->incomplete()['incomplete_count']);
    }

    public function testIncompleteLessonsSkipsLessonsWithoutAssignmentInternshipAndEmptySize(): void
    {
        $this->insertLesson('Atanmamış', 1, null, null, 0, 30);
        $internship = $this->insertLesson('Staj', 1, null, null, 0, 30, 4);
        $empty = $this->insertLesson('Mevcudu Yok', 1, null, null, 0, 0);
        $this->assign($internship);
        $this->assign($empty);

        $result = $this->incomplete();
        $this->assertSame(0, $result['incomplete_count']);
        $this->assertSame([], $result['programs']);
    }

    public function testIncompleteLessonsCountsGroupsAsOneLessonWithSummedSize(): void
    {
        $groupA = $this->insertLesson('Fizik A', 1, null, 'FIZ201', 1, 20);
        $groupB = $this->insertLesson('Fizik B', 1, null, 'FIZ201', 2, 25);
        $this->assign($groupA);
        $this->assign($groupB);

        $result = $this->incomplete();
        $this->assertSame(1, $result['lesson_count']);
        $this->assertSame(1, $result['incomplete_count']);
        $lesson = $result['programs'][0]['lessons'][0];
        $this->assertSame(45, $lesson['size']);
        $this->assertSame(['A', 'B'], $lesson['groups']);
    }

    public function testIncompleteLessonsEvaluatesCombinedExamUnderParent(): void
    {
        $parent = $this->insertLesson('Ortak Ders', 1, null, null, 0, 20);
        $child = $this->insertLesson('Ortak Ders (2. sınıf)', 3, null, null, 0, 15);
        $this->assign($parent);
        $this->assign($child);
        $this->insert('lesson_combinations', [
            'parent_lesson_id' => $parent, 'child_lesson_id' => $child, 'type' => 'exam',
            'semester' => self::SEMESTER, 'academic_year' => self::YEAR,
        ]);

        $result = $this->incomplete();
        // Alt ders ayrı sayılmaz; üst dersin içinde, toplam mevcutla (20 + 15) değerlendirilir
        $this->assertSame(1, $result['incomplete_count']);
        $lesson = $result['programs'][0]['lessons'][0];
        $this->assertSame($parent, $lesson['lesson_id']);
        $this->assertSame(35, $lesson['size']);
        $this->assertCount(1, $lesson['combined']);

        // Kapasite birleşik mevcudu karşılayınca tamamlanır
        $this->saveExamInClassroom($parent, $this->insertSchedule($this->progId, 1), $this->insertClassroom(35));
        $this->assertSame(0, $this->incomplete()['incomplete_count']);
    }

    public function testIncompleteLessonsRespectsProgramFilter(): void
    {
        $otherProgId = $this->insertProgram();
        $this->assign($this->insertLesson('Başka Program', 1, $otherProgId, null, 0, 10));

        $this->assertSame(0, $this->incomplete()['incomplete_count']);
        $this->assertSame(1, $this->analysis->incompleteLessons(ExamType::MIDTERM, self::SEMESTER, self::YEAR, [$otherProgId])['incomplete_count']);
        $this->assertSame(0, $this->analysis->incompleteLessons(ExamType::MIDTERM, self::SEMESTER, self::YEAR, [])['lesson_count']);
    }
}
