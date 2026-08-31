<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Enums\UserRole;
use App\Enums\UserTitle;
use App\Enums\UnitType;
use App\Enums\ExamType;
use App\Enums\ClassroomType;
use App\Enums\LessonType;
use App\Enums\OwnerType;
use App\Enums\PermissionType;
use App\Enums\ScheduleItemStatus;
use App\Enums\ScheduleNoteStatus;

class EnumTest extends BaseTestCase
{
    public function testUserRoleLabelsAndCases(): void
    {
        $this->assertEquals('Yönetici', UserRole::Admin->getLabel());
        $this->assertEquals('Akademisyen', UserRole::Lecturer->getLabel());
        $this->assertEquals('Bölüm Başkanı', UserRole::DepartmentHead->getLabel());
        $this->assertEquals('Müdür', UserRole::Manager->getLabel());
        $this->assertEquals('Müdür Yardımcısı', UserRole::SubManager->getLabel());
        $this->assertEquals('Sekreter', UserRole::Secretary->getLabel());
        $this->assertEquals('Mutemet', UserRole::PayrollOfficer->getLabel());
        $this->assertEquals('Araştırma Görevlisi', UserRole::ResearchAssistant->getLabel());
        $this->assertEquals('Kullanıcı', UserRole::User->getLabel());

        $this->assertEquals(UserRole::Admin, UserRole::fromLabel('Yönetici'));
        $this->assertEquals(UserRole::PayrollOfficer, UserRole::fromLabel('Mutemet'));
        $this->assertEquals(UserRole::Lecturer, UserRole::fromLabel('Akademisyen'));
        $this->assertNull(UserRole::fromLabel('Bilinmeyen Rol'));
    }

    public function testUserTitleParsing(): void
    {
        $parsed1 = UserTitle::parseAcademicName('Prof. Dr. Ahmet Yılmaz');
        $this->assertEquals('Prof. Dr.', $parsed1['title']);
        $this->assertEquals('Ahmet', $parsed1['name']);
        $this->assertEquals('Yılmaz', $parsed1['last_name']);

        $parsed2 = UserTitle::parseAcademicName('Öğr. Gör. Dr. Mehmet Ali Kaya');
        $this->assertEquals('Öğr. Gör. Dr.', $parsed2['title']);
        $this->assertEquals('Mehmet Ali', $parsed2['name']);
        $this->assertEquals('Kaya', $parsed2['last_name']);

        $parsed3 = UserTitle::parseAcademicName('Ayşe Demir');
        $this->assertEquals('', $parsed3['title']);
        $this->assertEquals('Ayşe', $parsed3['name']);
        $this->assertEquals('Demir', $parsed3['last_name']);

        $sorted = UserTitle::getSortedByLength();
        $this->assertIsArray($sorted);
        $this->assertGreaterThan(0, count($sorted));
        $this->assertEquals('Dr. Öğr. Üyesi', $sorted[0]);

        $this->assertGreaterThan(UserTitle::AssocProf->getHierarchyRank(), UserTitle::Prof->getHierarchyRank());
        $this->assertGreaterThan(UserTitle::AsstProf->getHierarchyRank(), UserTitle::AssocProf->getHierarchyRank());
        $this->assertGreaterThan(UserTitle::DrLecturer->getHierarchyRank(), UserTitle::AsstProf->getHierarchyRank());
        $this->assertGreaterThan(UserTitle::Lecturer->getHierarchyRank(), UserTitle::DrLecturer->getHierarchyRank());
        $this->assertGreaterThan(UserTitle::ResAssist->getHierarchyRank(), UserTitle::Lecturer->getHierarchyRank());
    }

    public function testUnitType(): void
    {
        $this->assertEquals('Meslek Yüksekokulu', UnitType::Vocational->getLabel());
        $this->assertEquals('Fakülte', UnitType::Faculty->getLabel());
        $this->assertEquals('Enstitü', UnitType::Institute->getLabel());
        $this->assertEquals('Yüksekokul', UnitType::School->getLabel());
        $this->assertEquals('Rektörlük', UnitType::Rectorate->getLabel());

        // Manager / SubManager Titles per UnitType
        $this->assertEquals('Dekan', UnitType::Faculty->getManagerTitle());
        $this->assertEquals('Dekan Yardımcısı', UnitType::Faculty->getSubManagerTitle());
        $this->assertEquals('Dekan Yardımcıları', UnitType::Faculty->getSubManagerTitle(true));
        $this->assertEquals('Müdür', UnitType::Vocational->getManagerTitle());
        $this->assertEquals('Müdür Yardımcısı', UnitType::Vocational->getSubManagerTitle());
        $this->assertEquals('Müdür Yardımcıları', UnitType::Vocational->getSubManagerTitle(true));
        $this->assertEquals('Müdür', UnitType::Institute->getManagerTitle());
        $this->assertEquals('Müdür Yardımcısı', UnitType::Institute->getSubManagerTitle());
        $this->assertEquals('Müdür Yardımcıları', UnitType::Institute->getSubManagerTitle(true));
        $this->assertEquals('Müdür', UnitType::School->getManagerTitle());
        $this->assertEquals('Müdür Yardımcısı', UnitType::School->getSubManagerTitle());
        $this->assertEquals('Müdür Yardımcıları', UnitType::School->getSubManagerTitle(true));
        $this->assertEquals('Rektör', UnitType::Rectorate->getManagerTitle());
        $this->assertEquals('Rektör Yardımcısı', UnitType::Rectorate->getSubManagerTitle());
        $this->assertEquals('Rektör Yardımcıları', UnitType::Rectorate->getSubManagerTitle(true));

        $this->assertEquals(UnitType::Vocational, UnitType::fromLabel('Meslek Yüksekokulu'));
        $this->assertNull(UnitType::fromLabel('Geçersiz'));

        $array = UnitType::toArray();
        $this->assertIsArray($array);
        $this->assertCount(5, $array);
        $this->assertEquals('fakulte', $array[0]['value']);
        $this->assertEquals('Fakülte', $array[0]['label']);
    }

    public function testExamType(): void
    {
        $this->assertEquals('Ara Sınav', ExamType::MIDTERM->label());
        $this->assertEquals('Final Sınavı', ExamType::FINAL->label());
        $this->assertEquals('Bütünleme Sınavı', ExamType::MAKEUP->label());

        $this->assertTrue(ExamType::isExamType('midterm-exam'));
        $this->assertFalse(ExamType::isExamType('invalid-type'));

        $this->assertEquals('midterm_start_date', ExamType::MIDTERM->startDateSettingKey());
        $this->assertContains('midterm-exam', ExamType::values());
    }

    public function testClassroomType(): void
    {
        $this->assertEquals(1, ClassroomType::CLASSROOM->value);
        $this->assertEquals(2, ClassroomType::COMPUTER_LAB->value);
        $this->assertEquals(3, ClassroomType::REMOTE_EDUCATION->value);
        $this->assertEquals(4, ClassroomType::HYBRID->value);

        $this->assertEquals('Derslik', ClassroomType::CLASSROOM->label());
        $this->assertEquals('Bilgisayar Laboratuvarı', ClassroomType::COMPUTER_LAB->label());

        $this->assertEquals(ClassroomType::CLASSROOM, ClassroomType::fromLabel('Derslik'));
        $this->assertNull(ClassroomType::fromLabel('Bilinmeyen'));
    }

    public function testLessonType(): void
    {
        $this->assertEquals(1, LessonType::COMPULSORY->value);
        $this->assertEquals(2, LessonType::ELECTIVE->value);
        $this->assertEquals(3, LessonType::UNIVERSITY_ELECTIVE->value);
        $this->assertEquals(4, LessonType::INTERNSHIP->value);

        $this->assertEquals('Zorunlu', LessonType::COMPULSORY->label());
        $this->assertEquals('Seçmeli', LessonType::ELECTIVE->label());

        $this->assertEquals(LessonType::COMPULSORY, LessonType::fromLabel('Zorunlu'));
        $this->assertNull(LessonType::fromLabel('Bilinmeyen'));
    }

    public function testOwnerType(): void
    {
        $this->assertEquals('user', OwnerType::USER->value);
        $this->assertEquals('lesson', OwnerType::LESSON->value);
        $this->assertEquals('program', OwnerType::PROGRAM->value);
        $this->assertEquals('classroom', OwnerType::CLASSROOM->value);
    }

    public function testPermissionType(): void
    {
        $this->assertEquals('view', PermissionType::VIEW->value);
        $this->assertEquals('create', PermissionType::CREATE->value);
        $this->assertEquals('update', PermissionType::UPDATE->value);
        $this->assertEquals('delete', PermissionType::DELETE->value);
        $this->assertEquals('list', PermissionType::LIST->value);

        $this->assertEquals('Görüntüle', PermissionType::VIEW->getLabel());

        $scopes = PermissionType::MANAGE_PROGRAM->getAllowedScopes();
        $this->assertContains('departments', $scopes);
        $this->assertContains('units', $scopes);

        $manageable = PermissionType::getManageablePermissions();
        $this->assertContains(PermissionType::MANAGE_LESSONS, $manageable);
        $this->assertContains(PermissionType::MANAGE_SCHEDULE, $manageable);
        $this->assertContains(PermissionType::PUBLISH_SCHEDULE, $manageable);
    }

    public function testScheduleItemStatus(): void
    {
        $this->assertEquals('single', ScheduleItemStatus::SINGLE->value);
        $this->assertEquals('group', ScheduleItemStatus::GROUP->value);
        $this->assertEquals('preferred', ScheduleItemStatus::PREFERRED->value);
        $this->assertEquals('unavailable', ScheduleItemStatus::UNAVAILABLE->value);
    }

    public function testScheduleNoteStatus(): void
    {
        $this->assertEquals('Beklemede', ScheduleNoteStatus::PENDING->getLabel());
        $this->assertEquals('Görüldü', ScheduleNoteStatus::READ->getLabel());
        $this->assertEquals('Gereği Yapıldı', ScheduleNoteStatus::COMPLETED->getLabel());
        $this->assertEquals('Reddedildi', ScheduleNoteStatus::REJECTED->getLabel());
        $this->assertEquals('Bilgi Verildi', ScheduleNoteStatus::INFO_SENT->getLabel());

        $this->assertEquals('text-bg-secondary', ScheduleNoteStatus::PENDING->getBadgeClass());
        $this->assertEquals('text-bg-success', ScheduleNoteStatus::COMPLETED->getBadgeClass());
        $this->assertEquals('text-bg-danger', ScheduleNoteStatus::REJECTED->getBadgeClass());
        $this->assertEquals('text-bg-info', ScheduleNoteStatus::INFO_SENT->getBadgeClass());
    }
}
