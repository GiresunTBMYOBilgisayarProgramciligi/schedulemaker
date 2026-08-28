<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\Schedule\AvailabilityService;
use App\DTOs\AvailabilityFilterDTO;
use App\Models\Schedule;
use App\Models\Classroom;
use App\Enums\ClassroomType;
use App\Enums\OwnerType;
use App\Enums\ScheduleItemStatus;

class AvailabilityServiceTest extends BaseTestCase
{
    private AvailabilityService $service;
    private int $unitId;
    private int $deptId;
    private int $progId;
    private int $buildingId;
    private int $scheduleId;
    private int $lecturerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityService();

        $this->unitId = $this->insert('units', [
            'name' => 'Avail Test Unit ' . rand(1000, 9999),
            'type' => 'myo',
            'active' => 1
        ]);
        $this->deptId = $this->insert('departments', [
            'name' => 'Avail Dept ' . rand(1000, 9999),
            'unit_id' => $this->unitId,
            'active' => 1
        ]);
        $this->progId = $this->insert('programs', [
            'name' => 'Avail Prog ' . rand(1000, 9999),
            'department_id' => $this->deptId,
            'active' => 1
        ]);
        $this->buildingId = $this->insert('buildings', [
            'name' => 'Avail Bldg ' . rand(1000, 9999),
            'unit_id' => $this->unitId
        ]);
        $this->lecturerId = $this->insert('users', [
            'name' => 'Avail',
            'last_name' => 'Lecturer',
            'mail' => 'avail_lec_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);

        $this->scheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => OwnerType::PROGRAM->value,
            'owner_id' => $this->progId,
            'semester_no' => 1,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);
    }

    public function testAvailableClassroomsRequiresScheduleId(): void
    {
        $dto = AvailabilityFilterDTO::fromArray([
            'schedule_id' => null
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Uygun derslikleri belirlemek için Program ID belirtilmelidir");
        $this->service->availableClassrooms($dto);
    }

    public function testAvailableClassroomsRequiresLessonId(): void
    {
        $dto = AvailabilityFilterDTO::fromArray([
            'schedule_id' => $this->scheduleId,
            'lesson_id' => null
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Derslik türünü belirlemek için ders ID belirtilmelidir");
        $this->service->availableClassrooms($dto);
    }

    public function testAvailableClassroomsFiltersByClassroomTypeAndBuilding(): void
    {
        // 1. İki sınıf oluşturalım: Biri Lab (type=2), diğeri Normal Derslik (type=1)
        $labClassroomId = $this->insert('classrooms', [
            'name' => 'Lab 101 ' . rand(100, 999),
            'type' => ClassroomType::COMPUTER_LAB->value,
            'class_size' => 40,
            'building_id' => $this->buildingId
        ]);
        $normalClassroomId = $this->insert('classrooms', [
            'name' => 'Derslik 101 ' . rand(100, 999),
            'type' => ClassroomType::CLASSROOM->value,
            'class_size' => 50,
            'building_id' => $this->buildingId
        ]);

        // 2. Lab gerektiren bir ders oluştur
        $labLessonId = $this->insert('lessons', [
            'name' => 'Bilgisayar Ağları Lab',
            'code' => 'BIL' . rand(100, 999),
            'hours' => 2,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'building_id' => $this->buildingId,
            'classroom_type' => ClassroomType::COMPUTER_LAB->value,
            'semester_no' => 1,
            'type' => 1
        ]);

        $dto = AvailabilityFilterDTO::fromArray([
            'schedule_id' => $this->scheduleId,
            'lesson_id' => $labLessonId,
            'day_index' => 1,
            'items' => json_encode([
                ['start_time' => '09:00:00', 'end_time' => '10:00:00']
            ])
        ]);

        $available = $this->service->availableClassrooms($dto);
        $availableIds = array_map(fn($c) => $c->id, $available);

        // Lab sınıfı listede olmalı, normal derslik olmamalı
        $this->assertContains($labClassroomId, $availableIds);
        $this->assertNotContains($normalClassroomId, $availableIds);
    }

    public function testAvailableClassroomsExcludesOverlappingClassrooms(): void
    {
        // 1. Bir sınıf oluştur
        $classroomId = $this->insert('classrooms', [
            'name' => 'Oda 201 ' . rand(100, 999),
            'type' => ClassroomType::CLASSROOM->value,
            'class_size' => 50,
            'building_id' => $this->buildingId
        ]);

        // 2. Sınıf için bir takvim ve çakışan bir slot oluştur (09:00 - 11:00)
        $classroomScheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => OwnerType::CLASSROOM->value,
            'owner_id' => $classroomId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);

        $this->insert('schedule_items', [
            'schedule_id' => $classroomScheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => ScheduleItemStatus::SINGLE->value,
            'data' => serialize(['lesson_id' => 1])
        ]);

        // 3. Normal derslik gerektiren bir ders
        $lessonId = $this->insert('lessons', [
            'name' => 'Fizik 1',
            'code' => 'FIZ' . rand(100, 999),
            'hours' => 2,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'building_id' => $this->buildingId,
            'classroom_type' => ClassroomType::CLASSROOM->value,
            'semester_no' => 1,
            'type' => 1
        ]);

        // 4. Çakışan saat için arama yap (10:00 - 12:00) -> Çakışmadan dolayı oda dönmemeli
        $dtoOverlap = AvailabilityFilterDTO::fromArray([
            'schedule_id' => $this->scheduleId,
            'lesson_id' => $lessonId,
            'day_index' => 1,
            'items' => json_encode([
                ['start_time' => '10:00:00', 'end_time' => '12:00:00']
            ])
        ]);
        $availableOverlap = $this->service->availableClassrooms($dtoOverlap);
        $availableOverlapIds = array_map(fn($c) => $c->id, $availableOverlap);
        $this->assertNotContains($classroomId, $availableOverlapIds);

        // 5. Çakışmayan saat için arama yap (13:00 - 15:00) -> Oda müsait olmalı
        $dtoFree = AvailabilityFilterDTO::fromArray([
            'schedule_id' => $this->scheduleId,
            'lesson_id' => $lessonId,
            'day_index' => 1,
            'items' => json_encode([
                ['start_time' => '13:00:00', 'end_time' => '15:00:00']
            ])
        ]);
        $availableFree = $this->service->availableClassrooms($dtoFree);
        $availableFreeIds = array_map(fn($c) => $c->id, $availableFree);
        $this->assertContains($classroomId, $availableFreeIds);
    }

    public function testAvailableLessonsInPreferenceModeReturnsDummyCards(): void
    {
        $schedule = (new Schedule())->find($this->scheduleId);
        $lessons = $this->service->availableLessons($schedule, preferenceMode: true);

        $this->assertCount(2, $lessons);
        $this->assertEquals(ScheduleItemStatus::PREFERRED->value, $lessons[0]->status);
        $this->assertEquals(ScheduleItemStatus::UNAVAILABLE->value, $lessons[1]->status);
        $this->assertTrue($lessons[0]->is_dummy);
        $this->assertTrue($lessons[1]->is_dummy);
    }

    public function testAvailableObserversExcludesOverlappingObservers(): void
    {
        // 1. İki hoca oluşturalım: Hoca A (boşta), Hoca B (o saatte meşgul)
        $lecturerAId = $this->insert('users', [
            'name' => 'Gözetmen A',
            'last_name' => 'Test',
            'mail' => 'obs_a_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);
        $lecturerBId = $this->insert('users', [
            'name' => 'Gözetmen B',
            'last_name' => 'Test',
            'mail' => 'obs_b_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);

        // Hoca B için çakışan slot oluştur (09:00 - 11:00)
        $userBScheduleId = $this->insert('schedules', [
            'type' => 'final-exam',
            'owner_type' => OwnerType::USER->value,
            'owner_id' => $lecturerBId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);
        $this->insert('schedule_items', [
            'schedule_id' => $userBScheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'status' => ScheduleItemStatus::SINGLE->value,
            'data' => serialize(['lesson_id' => 1])
        ]);

        // 2. 10:00 - 12:00 saatleri için gözetmen ara
        $dto = AvailabilityFilterDTO::fromArray([
            'academic_year' => '2025 - 2026',
            'semester' => 'Güz',
            'type' => 'final-exam',
            'day_index' => 1,
            'items' => json_encode([
                ['start_time' => '10:00:00', 'end_time' => '12:00:00']
            ])
        ]);

        $observers = $this->service->availableObservers($dto);
        $observerIds = array_map(fn($u) => $u->id, $observers);

        // Hoca A müsait olmalı, meşgul olan Hoca B listeden elenmeli
        $this->assertContains($lecturerAId, $observerIds);
        $this->assertNotContains($lecturerBId, $observerIds);
    }
}
