<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Models\Lesson;

class LessonExamTotalSizeTest extends BaseTestCase
{
    private int $programId;

    protected function setUp(): void
    {
        parent::setUp();

        $unitId = $this->insert('units', [
            'name' => 'Test Unit ' . rand(1000, 9999),
            'type' => 'myo',
            'active' => 1
        ]);
        $deptId = $this->insert('departments', [
            'name' => 'Test Dept ' . rand(1000, 9999),
            'unit_id' => $unitId,
            'active' => 1
        ]);
        $this->programId = $this->insert('programs', [
            'name' => 'Test Program ' . rand(1000, 9999),
            'department_id' => $deptId,
            'active' => 1
        ]);
    }

    private function createLesson(int $size, int $groupNo = 0, string $code = null): int
    {
        return $this->insert('lessons', [
            'code' => $code ?? 'SZ' . rand(10000, 99999),
            'name' => 'Ders',
            'size' => $size,
            'group_no' => $groupNo,
            'program_id' => $this->programId,
            'semester_no' => 1,
        ]);
    }

    private function examLink(int $parentId, int $childId): void
    {
        $this->insert('lesson_combinations', [
            'parent_lesson_id' => $parentId,
            'child_lesson_id' => $childId,
            'type' => 'exam',
            'semester' => getSettingValue('semester'),
            'academic_year' => getSettingValue('academic_year'),
        ]);
    }

    private function load(int $id): Lesson
    {
        return (new Lesson())->where(['id' => $id])->first();
    }

    public function testBirlesmemisDersinToplamiKendiMevcuduDur(): void
    {
        $id = $this->createLesson(64);

        $this->assertSame(64, $this->load($id)->getExamTotalSize());
    }

    public function testBirlestirilmisDersTumBagliDerslerinMevcuduToplar(): void
    {
        $parent = $this->createLesson(64);
        $child1 = $this->createLesson(48);
        $child2 = $this->createLesson(44);
        $this->examLink($parent, $child1);
        $this->examLink($parent, $child2);

        $this->assertSame(156, $this->load($parent)->getExamTotalSize());
        // Alt ders de aynı toplam grubun parçası olarak aynı değeri vermeli
        $this->assertSame(156, $this->load($child1)->getExamTotalSize());
    }

    public function testAyniKodluDigerGruplarToplamaDahildir(): void
    {
        $code = 'GRP' . rand(10000, 99999);
        $groupA = $this->createLesson(30, 1, $code);
        $groupB = $this->createLesson(14, 2, $code);
        $childOfB = $this->createLesson(20);
        $this->examLink($groupB, $childOfB);

        $this->assertSame(64, $this->load($groupA)->getExamTotalSize());
    }
}
