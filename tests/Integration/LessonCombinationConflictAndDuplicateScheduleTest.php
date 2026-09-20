<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\DTOs\LessonDTO;
use App\DTOs\CombineLessonDTO;
use App\Services\LessonService;
use App\Exceptions\ScheduleConflictException;
use App\Enums\ScheduleItemStatus;

class LessonCombinationConflictAndDuplicateScheduleTest extends BaseTestCase
{
    private LessonService $lessonService;
    private int $deptId;
    private int $progId1;
    private int $progId2;
    private int $lecturerId;
    private int $buildingId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lessonService = new LessonService();

        $unitId = $this->insert('units', [
            'name' => 'Test Unit ' . rand(1000, 9999),
            'type' => 'myo',
            'active' => 1
        ]);
        $this->deptId = $this->insert('departments', [
            'name' => 'Test Dept ' . rand(1000, 9999),
            'unit_id' => $unitId,
            'active' => 1
        ]);
        $this->progId1 = $this->insert('programs', [
            'name' => 'Program 1 ' . rand(1000, 9999),
            'department_id' => $this->deptId,
            'active' => 1
        ]);
        $this->progId2 = $this->insert('programs', [
            'name' => 'Program 2 ' . rand(1000, 9999),
            'department_id' => $this->deptId,
            'active' => 1
        ]);
        $this->buildingId = $this->insert('buildings', [
            'name' => 'Test Building ' . rand(1000, 9999),
            'unit_id' => $unitId
        ]);
        $this->lecturerId = $this->insert('users', [
            'name' => 'Hoca',
            'last_name' => 'Test',
            'mail' => 'lecturer_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $unitId
        ]);
    }

    public function testUserScheduleFirstOrCreateDoesNotCreateDuplicateAndHealsSemesterNo(): void
    {
        // 1. Legacy olarak semester_no = 1 olan bir hoca schedule'ı oluştur
        $legacyScheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'user',
            'owner_id' => $this->lecturerId,
            'semester' => 'Güz',
            'academic_year' => '2025-2026',
            'semester_no' => 1
        ]);

        // 2. firstOrCreate çağır
        $schedule = (new Schedule())->firstOrCreate([
            'owner_type' => 'user',
            'owner_id' => $this->lecturerId,
            'semester' => 'Güz',
            'academic_year' => '2025-2026',
            'type' => 'lesson',
            'semester_no' => null
        ]);

        // 3. Aynı schedule dönmeli, yenisi oluşturulmamalı
        $this->assertEquals($legacyScheduleId, $schedule->id);

        // 4. DB'de toplamda sadece 1 schedule olmalı ve semester_no 0'a auto-heal edilmiş olmalı
        $stmt = $this->getDb()->prepare("SELECT * FROM schedules WHERE owner_type = 'user' AND owner_id = ?");
        $stmt->execute([$this->lecturerId]);
        $rows = $stmt->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertEquals(0, (int)$rows[0]['semester_no']);
    }

    public function testUserScheduleCreateForcesSemesterNo0(): void
    {
        $sch = new Schedule();
        $sch->owner_type = 'user';
        $sch->owner_id = $this->lecturerId;
        $sch->semester = 'Bahar';
        $sch->academic_year = '2025-2026';
        $sch->type = 'lesson';
        $sch->semester_no = 2; // Zorla değer verildi
        $sch->create();

        $this->assertNotNull($sch->id);

        $stmt = $this->getDb()->prepare("SELECT semester_no FROM schedules WHERE id = ?");
        $stmt->execute([$sch->id]);
        $semesterNo = $stmt->fetchColumn();

        $this->assertEquals(0, (int)$semesterNo, "Hoca schedule'ı için semester_no veritabanında 0 olmalıdır.");
    }

    public function testCombineLessonDoesNotDuplicateOrGroupLecturerScheduleItems(): void
    {
        // Parent ders: Program 1'de 2 saatlik ders
        $parentDto = LessonDTO::fromArray([
            'code' => 'PAR' . rand(100, 999),
            'name' => 'Grafik ve Animasyon',
            'group_no' => 0,
            'size' => 40,
            'hours' => 2,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId1,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025-2026',
            'building_id' => $this->buildingId
        ]);
        $parentId = $this->lessonService->saveNew($parentDto);

        // Child ders: Program 2'de 2 saatlik ders
        $childDto = LessonDTO::fromArray([
            'code' => 'CHL' . rand(100, 999),
            'name' => 'Grafik ve Animasyon Alt',
            'group_no' => 0,
            'size' => 30,
            'hours' => 2,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId2,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025-2026',
            'building_id' => $this->buildingId
        ]);
        $childId = $this->lessonService->saveNew($childDto);

        // Parent ders için ders programı ve hoca programı oluştur
        $parentScheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'lesson',
            'owner_id' => $parentId,
            'semester' => 'Güz',
            'academic_year' => '2025-2026',
            'semester_no' => 0
        ]);

        $lecturerScheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'user',
            'owner_id' => $this->lecturerId,
            'semester' => 'Güz',
            'academic_year' => '2025-2026',
            'semester_no' => 0
        ]);

        // Parent ders için Salı 08:00-09:50 arası item oluştur
        $itemData = [['lesson_id' => $parentId, 'lecturer_id' => $this->lecturerId, 'classroom_id' => null]];
        $this->insert('schedule_items', [
            'schedule_id' => $parentScheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:50:00',
            'status' => 'single',
            'data' => serialize($itemData)
        ]);

        // Hoca programında da parent ders için öğe var
        $this->insert('schedule_items', [
            'schedule_id' => $lecturerScheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:50:00',
            'status' => 'single',
            'data' => serialize($itemData)
        ]);

        // Dersleri birleştir
        $combineDto = new CombineLessonDTO(
            parentId: $parentId,
            childId: $childId,
            itemsToRemove: [],
            semester: 'Güz',
            academicYear: '2025-2026'
        );
        $this->lessonService->combineLesson($combineDto);

        // Hoca programındaki öğeyi incele:
        // 1. Status 'single' kalmalı (sahte 'group' olmamalı)
        // 2. Data içinde sadece 1 ders olmalı (üst ders), child ders içine merge edilmemeli
        $stmt = $this->getDb()->prepare("SELECT * FROM schedule_items WHERE schedule_id = ?");
        $stmt->execute([$lecturerScheduleId]);
        $lecturerItems = $stmt->fetchAll();

        $this->assertCount(1, $lecturerItems, "Hoca programında yalnızca 1 öğe olmalıdır.");
        $this->assertEquals(ScheduleItemStatus::SINGLE->value, $lecturerItems[0]['status'], "Hoca programındaki öğe 'group' değil 'single' olmalıdır.");

        $data = unserialize($lecturerItems[0]['data']);
        $this->assertCount(1, $data, "Hoca programındaki öğe data alanında sadece tek ders barındırmalıdır.");
        $this->assertEquals($parentId, $data[0]['lesson_id']);

        // Child dersin programında (progId2) ise dersin eklendiğini doğrula
        $stmtProg = $this->getDb()->prepare(
            "SELECT si.* FROM schedule_items si 
             JOIN schedules s ON si.schedule_id = s.id 
             WHERE s.owner_type = 'program' AND s.owner_id = ? AND s.semester_no = 1"
        );
        $stmtProg->execute([$this->progId2]);
        $childProgItems = $stmtProg->fetchAll();

        $this->assertNotEmpty($childProgItems, "Child dersin programında ders yer almalıdır.");
    }

    public function testCombineLessonThrowsConflictExceptionWhenChildProgramHasOverlappingLesson(): void
    {
        // Parent ders: Program 1'de 2 saatlik ders
        $parentDto = LessonDTO::fromArray([
            'code' => 'PAR_CONF' . rand(100, 999),
            'name' => 'Türk Dili',
            'group_no' => 0,
            'size' => 40,
            'hours' => 2,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId1,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025-2026',
            'building_id' => $this->buildingId
        ]);
        $parentId = $this->lessonService->saveNew($parentDto);

        // Child ders: Program 2'de 2 saatlik ders
        $childDto = LessonDTO::fromArray([
            'code' => 'CHL_CONF' . rand(100, 999),
            'name' => 'Türk Dili Alt',
            'group_no' => 0,
            'size' => 30,
            'hours' => 2,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId2,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025-2026',
            'building_id' => $this->buildingId
        ]);
        $childId = $this->lessonService->saveNew($childDto);

        // Program 2'de başka bir ders (örnek: Matematik)
        $otherLessonDto = LessonDTO::fromArray([
            'code' => 'MAT' . rand(100, 999),
            'name' => 'Matematik',
            'group_no' => 0,
            'size' => 30,
            'hours' => 2,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => $this->lecturerId,
            'department_id' => $this->deptId,
            'program_id' => $this->progId2,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025-2026',
            'building_id' => $this->buildingId
        ]);
        $otherLessonId = $this->lessonService->saveNew($otherLessonDto);

        // Parent ders için Salı 08:00-09:50 arası schedule oluştur
        $parentScheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'lesson',
            'owner_id' => $parentId,
            'semester' => 'Güz',
            'academic_year' => '2025-2026',
            'semester_no' => 0
        ]);
        $this->insert('schedule_items', [
            'schedule_id' => $parentScheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:50:00',
            'status' => 'single',
            'data' => serialize([['lesson_id' => $parentId, 'lecturer_id' => $this->lecturerId, 'classroom_id' => null]])
        ]);

        // Program 2'nin programına Matematik dersini Salı 08:00-09:50 olarak yerleştir (ÇAKIŞMA)
        $prog2ScheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => $this->progId2,
            'semester' => 'Güz',
            'academic_year' => '2025-2026',
            'semester_no' => 1
        ]);
        $this->insert('schedule_items', [
            'schedule_id' => $prog2ScheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:50:00',
            'status' => 'single',
            'data' => serialize([['lesson_id' => $otherLessonId, 'lecturer_id' => $this->lecturerId, 'classroom_id' => null]])
        ]);

        // 1. previewCombineLesson çağrıldığında ScheduleConflictException fırlatılmalı
        $combineDto = new CombineLessonDTO(
            parentId: $parentId,
            childId: $childId,
            itemsToRemove: [],
            semester: 'Güz',
            academicYear: '2025-2026'
        );

        try {
            $this->lessonService->previewCombineLesson($combineDto);
            $this->fail("previewCombineLesson çakışma durumunda ScheduleConflictException fırlatmalıydı.");
        } catch (ScheduleConflictException $e) {
            $this->assertStringContainsString('çakışma tespit edildi', $e->getMessage());
            $this->assertStringContainsString('Matematik', $e->getMessage());
        }

        // 2. combineLesson çağrıldığında da ScheduleConflictException fırlatılmalı ve veri bozulmamalı
        try {
            $this->lessonService->combineLesson($combineDto);
            $this->fail("combineLesson çakışma durumunda ScheduleConflictException fırlatmalıydı.");
        } catch (ScheduleConflictException $e) {
            $this->assertStringContainsString('çakışma tespit edildi', $e->getMessage());
        }

        // 3. Child'ın programında Matematik dersinin tek başına kaldığı ve grup dersine dönüşmediği doğrulanmalı
        $stmtProg = $this->getDb()->prepare(
            "SELECT si.* FROM schedule_items si 
             WHERE si.schedule_id = ?"
        );
        $stmtProg->execute([$prog2ScheduleId]);
        $items = $stmtProg->fetchAll();

        $this->assertCount(1, $items);
        $this->assertEquals(ScheduleItemStatus::SINGLE->value, $items[0]['status']);
        $itemData = unserialize($items[0]['data']);
        $this->assertCount(1, $itemData);
        $this->assertEquals($otherLessonId, $itemData[0]['lesson_id']);
    }
}
