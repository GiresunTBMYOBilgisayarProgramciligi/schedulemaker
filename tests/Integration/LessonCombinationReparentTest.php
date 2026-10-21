<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Models\Lesson;
use App\Repositories\LessonCombinationRepository;

class LessonCombinationReparentTest extends BaseTestCase
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

    private function createLesson(string $code): int
    {
        return $this->insert('lessons', [
            'code' => $code . rand(1000, 9999),
            'name' => 'Ders ' . $code,
            'program_id' => $this->programId,
            'semester_no' => 1,
        ]);
    }

    private function createLink(int $parentId, int $childId, string $type): int
    {
        return $this->insert('lesson_combinations', [
            'parent_lesson_id' => $parentId,
            'child_lesson_id' => $childId,
            'type' => $type,
            'semester' => getSettingValue('semester'),
            'academic_year' => getSettingValue('academic_year'),
        ]);
    }

    private function parentOf(int $combinationId): int
    {
        $stmt = $this->getDb()->prepare("SELECT parent_lesson_id FROM lesson_combinations WHERE id = ?");
        $stmt->execute([$combinationId]);
        return (int)$stmt->fetchColumn();
    }

    public function testReparentExamChildMovesOnlyExamLink(): void
    {
        $oldParent = $this->createLesson('OLD');
        $newParent = $this->createLesson('NEW');
        $grandChild = $this->createLesson('GC');

        $examLink = $this->createLink($oldParent, $grandChild, 'exam');
        $lessonLink = $this->createLink($oldParent, $grandChild, 'lesson');

        (new LessonCombinationRepository())->reparentExamChild($grandChild, $newParent, getSettingValue('semester'), getSettingValue('academic_year'));

        $this->assertSame($newParent, $this->parentOf($examLink));
        $this->assertSame($oldParent, $this->parentOf($lessonLink));
    }

    public function testReparentChildMovesAllLinks(): void
    {
        $oldParent = $this->createLesson('OLD');
        $newParent = $this->createLesson('NEW');
        $grandChild = $this->createLesson('GC');

        $examLink = $this->createLink($oldParent, $grandChild, 'exam');
        $lessonLink = $this->createLink($oldParent, $grandChild, 'lesson');

        (new LessonCombinationRepository())->reparentChild($grandChild, $newParent, getSettingValue('semester'), getSettingValue('academic_year'));

        $this->assertSame($newParent, $this->parentOf($examLink));
        $this->assertSame($newParent, $this->parentOf($lessonLink));
    }

    public function testGetExamLinkedLessonIdsReturnsAllChildren(): void
    {
        $parent = $this->createLesson('P');
        $children = [];
        for ($i = 0; $i < 3; $i++) {
            $children[] = $child = $this->createLesson('C' . $i);
            $this->createLink($parent, $child, 'exam');
        }

        $expected = array_merge([$parent], $children);
        sort($expected);

        foreach ([$parent, $children[0]] as $lessonId) {
            $ids = (new Lesson())->find($lessonId)->getExamLinkedLessonIds();
            sort($ids);
            $this->assertSame($expected, array_map('intval', $ids));
        }
    }
}
