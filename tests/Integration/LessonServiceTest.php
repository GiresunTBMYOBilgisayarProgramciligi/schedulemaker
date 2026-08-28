<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\LessonService;
use App\DTOs\LessonDTO;
use App\DTOs\CombineLessonDTO;
use App\Models\Lesson;

class LessonServiceTest extends BaseTestCase
{
    private LessonService $service;
    private int $unitId;
    private int $deptId;
    private int $progId;
    private int $lecturerId;
    private int $buildingId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LessonService();

        $this->unitId = $this->insert('units', [
            'name' => 'Lesson Test Unit ' . rand(1000, 9999),
            'type' => 'myo',
            'active' => 1
        ]);
        $this->deptId = $this->insert('departments', [
            'name' => 'Lesson Test Dept ' . rand(1000, 9999),
            'unit_id' => $this->unitId,
            'active' => 1
        ]);
        $this->progId = $this->insert('programs', [
            'name' => 'Lesson Test Prog ' . rand(1000, 9999),
            'department_id' => $this->deptId,
            'active' => 1
        ]);
        $this->buildingId = $this->insert('buildings', [
            'name' => 'Lesson Test Building ' . rand(1000, 9999),
            'unit_id' => $this->unitId
        ]);
        $this->lecturerId = $this->insert('users', [
            'name' => 'Hoca',
            'last_name' => 'Test',
            'mail' => 'hoca_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);
    }

    public function testSaveNewLessonAndAssignment(): void
    {
        $code = 'BIL' . rand(100, 999);
        $dto = LessonDTO::fromArray([
            'code' => $code,
            'name' => 'Veri Yapıları',
            'group_no' => 1,
            'size' => 40,
            'hours' => 3,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025 - 2026',
            'building_id' => $this->buildingId
        ]);

        $lessonId = $this->service->saveNew($dto);
        $this->assertGreaterThan(0, $lessonId);

        $lesson = (new Lesson())->find($lessonId);
        $this->assertEquals($code, $lesson->code);
        $this->assertEquals('Veri Yapıları', $lesson->name);
        $this->assertEquals(3, $lesson->hours);
    }

    public function testDeleteLesson(): void
    {
        $code = 'DEL' . rand(100, 999);
        $dto = LessonDTO::fromArray([
            'code' => $code,
            'name' => 'Silinecek Ders',
            'group_no' => 1,
            'size' => 30,
            'hours' => 2,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025 - 2026',
            'building_id' => $this->buildingId
        ]);

        $lessonId = $this->service->saveNew($dto);
        $lesson = (new Lesson())->find($lessonId);

        $this->service->deleteLesson($lesson);
        $this->assertNull((new Lesson())->find($lessonId));
    }

    public function testCombineLessonsAndScheduleSync(): void
    {
        // 1. İki ders oluşturalım: Biri 4 saatlik Parent, diğeri 2 saatlik Child
        $parentDto = LessonDTO::fromArray([
            'code' => 'PRNT' . rand(100, 999),
            'name' => 'Ana Ders',
            'group_no' => 1,
            'size' => 40,
            'hours' => 4,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025 - 2026',
            'building_id' => $this->buildingId
        ]);
        $parentId = $this->service->saveNew($parentDto);

        $childDto = LessonDTO::fromArray([
            'code' => 'CHLD' . rand(100, 999),
            'name' => 'Bağlanan Ders',
            'group_no' => 1,
            'size' => 30,
            'hours' => 2,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025 - 2026',
            'building_id' => $this->buildingId
        ]);
        $childId = $this->service->saveNew($childDto);

        // 2. Birleştirme DTO
        $combineDto = new CombineLessonDTO(
            parentId: $parentId,
            childId: $childId,
            itemsToRemove: [],
            semester: 'Güz',
            academicYear: '2025 - 2026'
        );

        $this->service->combineLesson($combineDto);

        $stmt = $this->getDb()->prepare("SELECT * FROM lesson_combinations WHERE parent_lesson_id = ? AND child_lesson_id = ?");
        $stmt->execute([$parentId, $childId]);
        $this->assertNotEmpty($stmt->fetchAll());

        // 3. Kendisiyle birleştirmeyi engelleme
        $selfDto = new CombineLessonDTO(
            parentId: $parentId,
            childId: $parentId,
            itemsToRemove: [],
            semester: 'Güz',
            academicYear: '2025 - 2026'
        );
        $this->expectException(\Exception::class);
        $this->service->combineLesson($selfDto);
    }

    public function testDeleteParentLessonLink(): void
    {
        $parentDto = LessonDTO::fromArray([
            'code' => 'PRN2' . rand(100, 999),
            'name' => 'Ana Ders 2',
            'group_no' => 1,
            'size' => 40,
            'hours' => 4,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025 - 2026',
            'building_id' => $this->buildingId
        ]);
        $parentId = $this->service->saveNew($parentDto);

        $childDto = LessonDTO::fromArray([
            'code' => 'CHL2' . rand(100, 999),
            'name' => 'Bağlanan Ders 2',
            'group_no' => 1,
            'size' => 30,
            'hours' => 2,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025 - 2026',
            'building_id' => $this->buildingId
        ]);
        $childId = $this->service->saveNew($childDto);

        $combineDto = new CombineLessonDTO(
            parentId: $parentId,
            childId: $childId,
            itemsToRemove: [],
            semester: 'Güz',
            academicYear: '2025 - 2026'
        );
        $this->service->combineLesson($combineDto);

        // Bağlantıyı sil
        $deleteDto = new \App\DTOs\DeleteCombineLessonDTO(
            id: $childId,
            type: 'lesson',
            semester: 'Güz',
            academicYear: '2025 - 2026'
        );
        $this->service->deleteParentLesson($deleteDto);

        $stmt = $this->getDb()->prepare("SELECT * FROM lesson_combinations WHERE child_lesson_id = ? AND type = 'lesson'");
        $stmt->execute([$childId]);
        $this->assertEmpty($stmt->fetchAll());
    }
}
