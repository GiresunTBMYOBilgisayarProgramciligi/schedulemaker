<?php

namespace Tests\Unit;

use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\Enums\ClassroomType;
use App\Enums\LessonType;
use App\Enums\OwnerType;
use App\Middlewares\AuthMiddleware;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\User;
use App\Services\Export\Json\LessonScheduleJsonExporter;
use Tests\BaseTestCase;

class JsonExporterTest extends BaseTestCase
{
    private int $unitId;
    private int $buildingId;
    private int $uzemClassroomId;
    private int $normalClassroomId;
    private int $deptId;
    private int $programId;
    private int $lecturerId;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitId = $this->insert('units', [
            'name'   => 'Mühendislik Fakültesi ' . rand(1000, 9999),
            'type'   => 'fakulte',
            'active' => 1
        ]);

        $this->buildingId = $this->insert('buildings', [
            'name'    => 'Merkez Bina ' . rand(1000, 9999),
            'unit_id' => $this->unitId
        ]);

        $this->uzemClassroomId = $this->insert('classrooms', [
            'name'        => 'Uzem Sınıfı',
            'building_id' => $this->buildingId,
            'type'        => ClassroomType::REMOTE_EDUCATION->value
        ]);

        $this->normalClassroomId = $this->insert('classrooms', [
            'name'        => 'Derslik 101',
            'building_id' => $this->buildingId,
            'type'        => ClassroomType::CLASSROOM->value
        ]);

        $this->deptId = $this->insert('departments', [
            'name'    => 'Yazılım Mühendisliği ' . rand(1000, 9999),
            'unit_id' => $this->unitId,
            'active'  => 1
        ]);

        $this->programId = $this->insert('programs', [
            'name'          => 'Yazılım ' . rand(1000, 9999),
            'department_id' => $this->deptId,
            'active'        => 1
        ]);

        $this->lecturerId = $this->insert('users', [
            'name'          => 'Hoca',
            'last_name'     => 'Test',
            'mail'          => 'hoca_' . rand(1000, 9999) . '@test.com',
            'password'      => password_hash('123456', PASSWORD_DEFAULT),
            'role'          => 'lecturer',
            'department_id' => $this->deptId,
            'title'         => 'Dr. Öğr. Üyesi'
        ]);

        $this->adminId = $this->insert('users', [
            'name'      => 'Admin',
            'last_name' => 'User',
            'mail'      => 'admin_' . rand(1000, 9999) . '@test.com',
            'password'  => password_hash('123456', PASSWORD_DEFAULT),
            'role'      => 'admin'
        ]);
    }

    private function loginAs(int $userId): void
    {
        $this->resetAuth();
        $sessionKey = $_ENV["SESSION_KEY"] ?? 'user_id';
        $_SESSION[$sessionKey] = $userId;
    }

    /**
     * Program JSON çıktısında staj seçildiğinde staj dersleri birim binası ve uzem dersliği ile eklenmelidir.
     */
    public function testProgramJsonExportIncludesInternshipWithFallbackBuildingAndClassroom(): void
    {
        $this->loginAs($this->adminId);

        // 1) Normal ders
        $normalLessonId = $this->insert('lessons', [
            'name'           => 'Algoritmalar',
            'code'           => 'YAZ101',
            'type'           => LessonType::COMPULSORY->value,
            'program_id'     => $this->programId,
            'department_id'  => $this->deptId,
            'semester_no'    => 3,
            'hours'          => 2,
            'size'           => 30,
            'classroom_type' => ClassroomType::CLASSROOM->value,
            'building_id'    => $this->buildingId
        ]);

        // Program programı
        $progSchedId = $this->insert('schedules', [
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $this->programId,
            'semester_no'   => 3,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'is_published'  => 1
        ]);

        $this->insert('schedule_items', [
            'schedule_id' => $progSchedId,
            'day_index'   => 0,
            'start_time'  => '09:00:00',
            'end_time'    => '10:50:00',
            'status'      => 'single',
            'data'        => serialize([[
                'lesson_id'    => $normalLessonId,
                'lecturer_id'  => $this->lecturerId,
                'classroom_id' => $this->normalClassroomId
            ]])
        ]);

        // 2) Staj dersi (derslik atanmamış)
        $stajLessonId = $this->insert('lessons', [
            'name'           => 'Staj I',
            'code'           => 'YAZ.UYG-206',
            'type'           => LessonType::INTERNSHIP->value,
            'program_id'     => $this->programId,
            'department_id'  => $this->deptId,
            'semester_no'    => 3,
            'hours'          => 2,
            'size'           => 30,
            'classroom_type' => ClassroomType::REMOTE_EDUCATION->value,
            'building_id'    => $this->buildingId
        ]);

        $stajSchedId = $this->insert('schedules', [
            'owner_type'    => OwnerType::LESSON->value,
            'owner_id'      => $stajLessonId,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'is_published'  => 1
        ]);

        $this->insert('schedule_items', [
            'schedule_id' => $stajSchedId,
            'day_index'   => 1,
            'start_time'  => '14:00:00',
            'end_time'    => '15:50:00',
            'status'      => 'single',
            'data'        => serialize([[
                'lesson_id'    => $stajLessonId,
                'lecturer_id'  => $this->lecturerId,
                'classroom_id' => null
            ]])
        ]);

        $exporter = new LessonScheduleJsonExporter();
        $filters = ScheduleExportFilterDTO::fromArray([
            'owner_type'    => 'program',
            'owner_id'      => $this->programId,
            'semester_no'   => 3,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'show_internship' => true
        ]);
        $options = ScheduleExportOptionsDTO::fromArray([
            'show_internship' => true
        ]);

        $json = $exporter->getRawContent($filters, $options);
        $data = json_decode($json, true);

        $this->assertCount(2, $data);

        // İlk kayıt normal ders
        $this->assertSame('YAZ101', $data[0]['ders_kodu']);
        $this->assertSame('Derslik 101', $data[0]['derslik']);

        // İkinci kayıt staj dersi
        $this->assertSame('YAZ.UYG-206', $data[1]['ders_kodu']);
        $this->assertStringContainsString('Merkez Bina', $data[1]['bina']);
        $this->assertSame('Uzem Sınıfı', $data[1]['derslik']);
        $this->assertSame('Salı', $data[1]['gun']);
        $this->assertSame('14:00', $data[1]['baslangic_saati']);
        $this->assertSame('15:50', $data[1]['bitis_saati']);
    }

    /**
     * Staj seçeneği kapalıyken staj dersleri JSON çıktısına dahil edilmemelidir.
     */
    public function testProgramJsonExportExcludesInternshipWhenOptionIsFalse(): void
    {
        $this->loginAs($this->adminId);

        $normalLessonId = $this->insert('lessons', [
            'name'           => 'Algoritmalar 2',
            'code'           => 'YAZ102',
            'type'           => LessonType::COMPULSORY->value,
            'program_id'     => $this->programId,
            'department_id'  => $this->deptId,
            'semester_no'    => 3,
            'hours'          => 2,
            'size'           => 30,
            'classroom_type' => ClassroomType::CLASSROOM->value,
            'building_id'    => $this->buildingId
        ]);

        $progSchedId = $this->insert('schedules', [
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $this->programId,
            'semester_no'   => 3,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'is_published'  => 1
        ]);

        $this->insert('schedule_items', [
            'schedule_id' => $progSchedId,
            'day_index'   => 0,
            'start_time'  => '09:00:00',
            'end_time'    => '10:50:00',
            'status'      => 'single',
            'data'        => serialize([[
                'lesson_id'    => $normalLessonId,
                'lecturer_id'  => $this->lecturerId,
                'classroom_id' => $this->normalClassroomId
            ]])
        ]);

        $stajLessonId = $this->insert('lessons', [
            'name'           => 'Staj II',
            'code'           => 'YAZ.UYG-208',
            'type'           => LessonType::INTERNSHIP->value,
            'program_id'     => $this->programId,
            'department_id'  => $this->deptId,
            'semester_no'    => 3,
            'hours'          => 2,
            'size'           => 30,
            'classroom_type' => ClassroomType::REMOTE_EDUCATION->value,
            'building_id'    => $this->buildingId
        ]);

        $stajSchedId = $this->insert('schedules', [
            'owner_type'    => OwnerType::LESSON->value,
            'owner_id'      => $stajLessonId,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'is_published'  => 1
        ]);

        $this->insert('schedule_items', [
            'schedule_id' => $stajSchedId,
            'day_index'   => 1,
            'start_time'  => '14:00:00',
            'end_time'    => '15:50:00',
            'status'      => 'single',
            'data'        => serialize([[
                'lesson_id'    => $stajLessonId,
                'lecturer_id'  => $this->lecturerId,
                'classroom_id' => null
            ]])
        ]);

        $exporter = new LessonScheduleJsonExporter();
        $filters = ScheduleExportFilterDTO::fromArray([
            'owner_type'    => 'program',
            'owner_id'      => $this->programId,
            'semester_no'   => 3,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'show_internship' => false
        ]);
        $options = ScheduleExportOptionsDTO::fromArray([
            'show_internship' => false
        ]);

        $json = $exporter->getRawContent($filters, $options);
        $data = json_decode($json, true);

        $this->assertCount(1, $data);
        $this->assertSame('YAZ102', $data[0]['ders_kodu']);
    }

    /**
     * Staj dersinde özel bir derslik tanımlanmışsa o dersliğin bilgileri korunmalıdır.
     */
    public function testProgramJsonExportRetainsDefinedClassroomForInternship(): void
    {
        $this->loginAs($this->adminId);

        $progSchedId = $this->insert('schedules', [
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $this->programId,
            'semester_no'   => 3,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'is_published'  => 1
        ]);

        $stajLessonId = $this->insert('lessons', [
            'name'           => 'Staj Özel Sınıf',
            'code'           => 'YAZ.UYG-210',
            'type'           => LessonType::INTERNSHIP->value,
            'program_id'     => $this->programId,
            'department_id'  => $this->deptId,
            'semester_no'    => 3,
            'hours'          => 2,
            'size'           => 30,
            'classroom_type' => ClassroomType::REMOTE_EDUCATION->value,
            'building_id'    => $this->buildingId
        ]);

        $stajSchedId = $this->insert('schedules', [
            'owner_type'    => OwnerType::LESSON->value,
            'owner_id'      => $stajLessonId,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'is_published'  => 1
        ]);

        // Belirli bir derslik (Derslik 101) atanmış
        $this->insert('schedule_items', [
            'schedule_id' => $stajSchedId,
            'day_index'   => 2,
            'start_time'  => '10:00:00',
            'end_time'    => '11:50:00',
            'status'      => 'single',
            'data'        => serialize([[
                'lesson_id'    => $stajLessonId,
                'lecturer_id'  => $this->lecturerId,
                'classroom_id' => $this->normalClassroomId
            ]])
        ]);

        $exporter = new LessonScheduleJsonExporter();
        $filters = ScheduleExportFilterDTO::fromArray([
            'owner_type'    => 'program',
            'owner_id'      => $this->programId,
            'semester_no'   => 3,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'show_internship' => true
        ]);
        $options = ScheduleExportOptionsDTO::fromArray([
            'show_internship' => true
        ]);

        $json = $exporter->getRawContent($filters, $options);
        $data = json_decode($json, true);

        $this->assertCount(1, $data);
        $this->assertSame('YAZ.UYG-210', $data[0]['ders_kodu']);
        $this->assertSame('Derslik 101', $data[0]['derslik']);
    }

    /**
     * Misafir kullanıcılar staj seçeneğini gönderseler dahi staj derslerini görememelidir.
     */
    public function testGuestCannotViewInternshipInJsonExportEvenIfOptionTrue(): void
    {
        $this->resetAuth();

        $progSchedId = $this->insert('schedules', [
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $this->programId,
            'semester_no'   => 3,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'is_published'  => 1
        ]);

        $stajLessonId = $this->insert('lessons', [
            'name'           => 'Staj Gizli',
            'code'           => 'YAZ.UYG-212',
            'type'           => LessonType::INTERNSHIP->value,
            'program_id'     => $this->programId,
            'department_id'  => $this->deptId,
            'semester_no'    => 3,
            'hours'          => 2,
            'size'           => 30,
            'classroom_type' => ClassroomType::REMOTE_EDUCATION->value,
            'building_id'    => $this->buildingId
        ]);

        $stajSchedId = $this->insert('schedules', [
            'owner_type'    => OwnerType::LESSON->value,
            'owner_id'      => $stajLessonId,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'is_published'  => 1
        ]);

        $this->insert('schedule_items', [
            'schedule_id' => $stajSchedId,
            'day_index'   => 2,
            'start_time'  => '10:00:00',
            'end_time'    => '11:50:00',
            'status'      => 'single',
            'data'        => serialize([[
                'lesson_id'    => $stajLessonId,
                'lecturer_id'  => $this->lecturerId,
                'classroom_id' => null
            ]])
        ]);

        $exporter = new LessonScheduleJsonExporter();
        $filters = ScheduleExportFilterDTO::fromArray([
            'owner_type'    => 'program',
            'owner_id'      => $this->programId,
            'semester_no'   => 3,
            'type'          => 'lesson',
            'semester'      => 'Güz',
            'academic_year' => '2026 - 2027',
            'show_internship' => true
        ]);
        $options = ScheduleExportOptionsDTO::fromArray([
            'show_internship' => true
        ]);

        $json = $exporter->getRawContent($filters, $options);
        $data = json_decode($json, true);

        // Misafir kullanıcı staj derslerini göremez
        $this->assertCount(0, $data);
    }
}
