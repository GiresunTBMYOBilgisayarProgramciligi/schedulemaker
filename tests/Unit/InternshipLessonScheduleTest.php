<?php

namespace Tests\Unit;

use App\Enums\LessonType;
use App\Enums\OwnerType;
use App\Helpers\ScheduleViewHelper;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\User;
use App\Services\Schedule\AvailabilityService;
use App\Services\Schedule\ConflictResolver;
use Tests\BaseTestCase;

class InternshipLessonScheduleTest extends BaseTestCase
{
    private function createLecturer(string $name, string $lastName): User
    {
        $user = new User();
        $user->fill([
            'name'      => $name,
            'last_name' => $lastName,
            'mail'      => strtolower($name . '.' . $lastName . rand(100, 9999) . '@test.com'),
            'password'  => password_hash('123456', PASSWORD_DEFAULT),
            'role'      => 'lecturer',
            'title'     => 'Öğr. Gör.'
        ]);
        $user->create();
        return $user;
    }

    private function createLesson(string $name, int $type, int $groupNo = 0): Lesson
    {
        $lesson = new Lesson();
        $lesson->fill([
            'name'        => $name,
            'code'        => 'TEST' . rand(100, 999),
            'type'        => $type,
            'group_no'    => $groupNo,
            'semester_no' => 3,
            'hours'       => 2,
            'size'        => 30
        ]);
        $lesson->create();
        return $lesson;
    }

    /**
     * Program programında normal ders ile staj dersi aynı saatte çakışmamalıdır.
     */
    public function testProgramScheduleDoesNotConflictBetweenNormalAndInternshipLesson(): void
    {
        $resolver = new ConflictResolver();
        $lecturer1 = $this->createLecturer('Ahmet', 'Yılmaz');
        $lecturer2 = $this->createLecturer('Mehmet', 'Kaya');

        $programSchedule = new Schedule();
        $programSchedule->owner_type = OwnerType::PROGRAM->value;
        $programSchedule->owner_id = 1;
        $programSchedule->semester_no = 3;

        // Mevcut normal ders
        $existingLesson = $this->createLesson('Veritabanı', LessonType::COMPULSORY->value, 0);

        $existingItem = new ScheduleItem();
        $existingItem->id = 501;
        $existingItem->status = 'single';
        $existingItem->start_time = '09:00:00';
        $existingItem->end_time = '12:00:00';
        $existingItem->data = [[
            'lesson_id' => $existingLesson->id,
            'lecturer_id' => $lecturer1->id,
            'classroom_id' => 1
        ]];

        // Yeni eklenen staj dersi
        $newInternshipLesson = $this->createLesson('Staj I', LessonType::INTERNSHIP->value, 1);

        $newItemData = [
            'id' => null,
            'day_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'status' => 'single',
            'data' => [[
                'lesson_id' => $newInternshipLesson->id,
                'lecturer_id' => $lecturer2->id,
                'classroom_id' => null
            ]]
        ];

        // Normal ders üzerine staj dersi eklenirken program seviyesinde çakışma olmamalı
        $error = $resolver->resolveConflict($newItemData, $existingItem, $newInternshipLesson, $programSchedule);
        $this->assertNull($error, "Program programında staj dersi normal ders ile çakışmamalıdır.");
    }

    /**
     * Program programında farklı gruplardaki staj dersleri aynı saatte çakışmamalıdır.
     */
    public function testProgramScheduleDoesNotConflictBetweenDifferentInternshipGroups(): void
    {
        $resolver = new ConflictResolver();
        $lecturer1 = $this->createLecturer('Ali', 'Demir');
        $lecturer2 = $this->createLecturer('Veli', 'Çelik');

        $programSchedule = new Schedule();
        $programSchedule->owner_type = OwnerType::PROGRAM->value;
        $programSchedule->owner_id = 1;
        $programSchedule->semester_no = 3;

        // Mevcut Staj Grup 1 dersi
        $existingLesson = $this->createLesson('Staj I', LessonType::INTERNSHIP->value, 1);

        $existingItem = new ScheduleItem();
        $existingItem->id = 502;
        $existingItem->status = 'group';
        $existingItem->start_time = '09:00:00';
        $existingItem->end_time = '12:00:00';
        $existingItem->data = [[
            'lesson_id' => $existingLesson->id,
            'lecturer_id' => $lecturer1->id,
            'classroom_id' => null
        ]];

        // Yeni eklenen Staj Grup 2 dersi
        $newInternshipLesson = $this->createLesson('Staj I', LessonType::INTERNSHIP->value, 2);

        $newItemData = [
            'id' => null,
            'day_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'status' => 'group',
            'data' => [[
                'lesson_id' => $newInternshipLesson->id,
                'lecturer_id' => $lecturer2->id,
                'classroom_id' => null
            ]]
        ];

        $error = $resolver->resolveConflict($newItemData, $existingItem, $newInternshipLesson, $programSchedule);
        $this->assertNull($error, "Farklı staj grupları aynı saatte çakışmamalıdır.");
    }

    /**
     * Program programında aynı staj grubu aynı saatte çakışmalıdır.
     */
    public function testProgramScheduleConflictsForSameInternshipGroup(): void
    {
        $resolver = new ConflictResolver();
        $lecturer1 = $this->createLecturer('Can', 'Oz');
        $lecturer2 = $this->createLecturer('Cem', 'Oz');

        $programSchedule = new Schedule();
        $programSchedule->owner_type = OwnerType::PROGRAM->value;
        $programSchedule->owner_id = 1;
        $programSchedule->semester_no = 3;

        // Mevcut Staj Grup 1 dersi
        $existingLesson = $this->createLesson('Staj I', LessonType::INTERNSHIP->value, 1);

        $existingItem = new ScheduleItem();
        $existingItem->id = 503;
        $existingItem->status = 'group';
        $existingItem->start_time = '09:00:00';
        $existingItem->end_time = '12:00:00';
        $existingItem->data = [[
            'lesson_id' => $existingLesson->id,
            'lecturer_id' => $lecturer1->id,
            'classroom_id' => null
        ]];

        // Aynı Staj Grup 1 dersi
        $newInternshipLesson = $this->createLesson('Staj I', LessonType::INTERNSHIP->value, 1);

        $newItemData = [
            'id' => null,
            'day_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'status' => 'group',
            'data' => [[
                'lesson_id' => $newInternshipLesson->id,
                'lecturer_id' => $lecturer2->id,
                'classroom_id' => null
            ]]
        ];

        $error = $resolver->resolveConflict($newItemData, $existingItem, $newInternshipLesson, $programSchedule);
        $this->assertNotNull($error, "Aynı staj grubu için çakışma hatası verilmelidir.");
        $this->assertStringContainsString("Aynı staj grubu", $error);
    }

    /**
     * Öğretim elemanı programında staj dersi hocanın diğer dersleri ile kesinlikle çakışmalıdır.
     */
    public function testLecturerScheduleConflictsWhenLecturerHasAnotherClass(): void
    {
        $resolver = new ConflictResolver();
        $lecturer = $this->createLecturer('Hakan', 'Aydın');

        $lecturerSchedule = new Schedule();
        $lecturerSchedule->owner_type = OwnerType::USER->value;
        $lecturerSchedule->owner_id = $lecturer->id;

        // Hocanın mevcut dersi
        $existingLesson = $this->createLesson('Veritabanı', LessonType::COMPULSORY->value);

        $existingItem = new ScheduleItem();
        $existingItem->id = 504;
        $existingItem->status = 'single';
        $existingItem->start_time = '09:00:00';
        $existingItem->end_time = '12:00:00';
        $existingItem->data = [[
            'lesson_id' => $existingLesson->id,
            'lecturer_id' => $lecturer->id,
            'classroom_id' => 1
        ]];

        // Hocaya atanmak istenen staj dersi
        $newInternshipLesson = $this->createLesson('Staj I', LessonType::INTERNSHIP->value);

        $newItemData = [
            'id' => null,
            'day_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'status' => 'single',
            'data' => [[
                'lesson_id' => $newInternshipLesson->id,
                'lecturer_id' => $lecturer->id,
                'classroom_id' => null
            ]]
        ];

        $error = $resolver->resolveConflict($newItemData, $existingItem, $newInternshipLesson, $lecturerSchedule);
        $this->assertNotNull($error, "Öğretim elemanı programında staj dersi diğer derslerle çakışmalıdır.");
    }

    /**
     * Staj dersleri için uygun derslik listesi boş dönmelidir.
     */
    public function testAvailabilityServiceReturnsEmptyClassroomsForInternship(): void
    {
        $availabilityService = new AvailabilityService();
        $internshipLesson = $this->createLesson('Staj I', LessonType::INTERNSHIP->value);

        $schedule = new Schedule();
        $schedule->fill([
            'type' => 'lesson',
            'owner_type' => OwnerType::PROGRAM->value,
            'owner_id' => 1,
            'semester' => 'Güz',
            'academic_year' => '2026-2027'
        ]);
        $schedule->create();

        $classrooms = $availabilityService->availableClassrooms([
            'schedule_id' => $schedule->id,
            'lesson_id'   => $internshipLesson->id,
            'day_index'   => 0,
            'start_time'  => '09:00',
            'end_time'    => '12:00'
        ]);
        $this->assertEmpty($classrooms, "Staj dersleri derslik gerektirmediği için boş liste dönmelidir.");
    }

    /**
     * ScheduleViewHelper staj özetini sadece program programında doğru gruplamalıdır.
     */
    public function testScheduleViewHelperGeneratesInternshipSummary(): void
    {
        $lecturer = $this->createLecturer('Deniz', 'Gök');
        $programId = $this->insert('programs', [
            'name'          => 'Bilgisayar Programcılığı Test',
            'department_id' => 1,
            'active'        => 1
        ]);

        $internshipLesson = new Lesson();
        $internshipLesson->fill([
            'name'        => 'İşletmede Mesleki Eğitim',
            'code'        => 'TEST999',
            'type'        => LessonType::INTERNSHIP->value,
            'group_no'    => 1,
            'semester_no' => 3,
            'program_id'  => $programId,
            'hours'       => 2,
            'size'        => 30
        ]);
        $internshipLesson->create();

        // Lesson schedule oluştur
        $lessonSchedule = new Schedule();
        $lessonSchedule->fill([
            'type'          => 'lesson',
            'owner_type'    => OwnerType::LESSON->value,
            'owner_id'      => $internshipLesson->id,
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
            'semester_no'   => 3
        ]);
        $lessonSchedule->create();

        $item = new ScheduleItem();
        $item->fill([
            'schedule_id' => $lessonSchedule->id,
            'day_index'   => 3, // Perşembe
            'week_index'  => 0,
            'start_time'  => '08:00:00',
            'end_time'    => '17:00:00',
            'status'      => 'single',
            'data'        => [[
                'lesson_id'   => $internshipLesson->id,
                'lecturer_id' => $lecturer->id,
                'classroom_id'=> null
            ]]
        ]);
        $item->create();

        // 1) Program schedule için özet gelmeli
        $programSchedule = new Schedule();
        $programSchedule->fill([
            'type'          => 'lesson',
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $programId,
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
            'semester_no'   => 3
        ]);

        $summary = ScheduleViewHelper::getInternshipSummary($programSchedule);
        $this->assertCount(1, $summary);
        $this->assertEquals('İşletmede Mesleki Eğitim', $summary[0]['name']);
        $this->assertEquals('1. Grup', $summary[0]['group']);
        $this->assertStringContainsString('Perşembe', $summary[0]['slots']);

        // 2) Hoca programı için özet boş dönmeli
        $userSchedule = new Schedule();
        $userSchedule->fill([
            'type'          => 'lesson',
            'owner_type'    => OwnerType::USER->value,
            'owner_id'      => $lecturer->id,
            'semester'      => 'Güz',
            'academic_year' => '2025 - 2026',
            'semester_no'   => null
        ]);
        $this->assertEmpty(ScheduleViewHelper::getInternshipSummary($userSchedule));
    }

    /**
     * Staj dersi için program çakışma haritası hesaplanırken normal dersler çakışma hücresi oluşturmamalıdır.
     */
    public function testGetProgramAvailabilityDoesNotFlagNormalLessonsForInternship(): void
    {
        $lecturer = $this->createLecturer('Kemal', 'Yurt');
        $normalLesson = $this->createLesson('Algoritmalar', LessonType::COMPULSORY->value);
        $internshipLesson = $this->createLesson('Staj II', LessonType::INTERNSHIP->value, 1);

        // Program programı oluştur
        $programSchedule = new Schedule();
        $programSchedule->fill([
            'type'          => 'lesson',
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $normalLesson->program_id ?: 1,
            'semester'      => 'Güz',
            'academic_year' => '2026-2027',
            'semester_no'   => 3
        ]);
        $programSchedule->create();

        // Normal ders item'ı ekle
        $item = new ScheduleItem();
        $item->fill([
            'schedule_id' => $programSchedule->id,
            'day_index'   => 3, // Perşembe
            'start_time'  => '08:00:00',
            'end_time'    => '12:00:00',
            'week_index'  => 0,
            'status'      => 'single',
            'data'        => [[
                'lesson_id'   => $normalLesson->id,
                'lecturer_id' => $lecturer->id,
                'classroom_id'=> 1
            ]]
        ]);
        $item->create();

        $availabilityService = new AvailabilityService();
        $res = $availabilityService->getProgramAvailability([
            'lesson_id'     => $internshipLesson->id,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026-2027',
            'owner_type'    => OwnerType::USER->value
        ]);

        $this->assertEmpty($res['unavailableCells'], "Normal dersler staj dersi için program çakışması üretmemelidir.");
    }
}
