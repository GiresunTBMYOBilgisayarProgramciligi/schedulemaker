<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Models\User;
use App\Models\Unit;
use App\Models\Department;
use App\Models\Program;
use App\Models\Building;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleNote;
use App\Policies\BuildingPolicy;
use App\Policies\ClassroomPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\ProgramPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\LessonPolicy;
use App\Policies\SettingPolicy;
use App\Policies\ScheduleNotePolicy;
use App\Policies\SchedulePolicy;
use App\DTOs\ScheduleNoteDTO;
use App\Enums\ScheduleNoteStatus;

class PolicyTest extends BaseTestCase
{
    private function createMockUser(string $role, ?int $unitId = null, ?int $deptId = null, ?int $progId = null): User
    {
        $user = new User();
        $user->id = rand(10000, 99999);
        $user->role = $role;
        $user->unit_id = $unitId;
        $user->department_id = $deptId;
        $user->program_id = $progId;
        $user->name = 'Test';
        $user->last_name = 'User';
        $user->mail = "user{$user->id}@example.com";
        return $user;
    }

    public function testAdminBypassAcrossPolicies(): void
    {
        $admin = $this->createMockUser('admin');

        $buildingPolicy = new BuildingPolicy();
        $this->assertTrue($admin->role === 'admin' && $buildingPolicy->before($admin, 'delete') === true);

        $settingPolicy = new SettingPolicy();
        $this->assertTrue($settingPolicy->before($admin, 'update') === true);

        $schedulePolicy = new SchedulePolicy();
        $this->assertTrue($schedulePolicy->before($admin, 'publish') === true);
    }

    public function testSettingPolicyBlocksNonAdmin(): void
    {
        $manager = $this->createMockUser('manager', 1);
        $policy = new SettingPolicy();

        $this->assertFalse($policy->list($manager));
        $this->assertFalse($policy->view($manager));
        $this->assertFalse($policy->create($manager));
    }

    public function testBuildingPolicy(): void
    {
        $policy = new BuildingPolicy();
        $secretary = $this->createMockUser('secretary', 1);
        $lecturer = $this->createMockUser('lecturer', 1);

        $buildingSameUnit = new Building();
        $buildingSameUnit->id = 10;
        $buildingSameUnit->unit_id = 1;

        $buildingOtherUnit = new Building();
        $buildingOtherUnit->id = 20;
        $buildingOtherUnit->unit_id = 2;

        $this->assertTrue($policy->view($secretary, $buildingSameUnit));
        $this->assertFalse($policy->view($secretary, $buildingOtherUnit));
        $this->assertFalse($policy->view($lecturer, $buildingOtherUnit));
    }

    public function testDepartmentPolicy(): void
    {
        $policy = new DepartmentPolicy();
        $deptHead = $this->createMockUser('department_head', 1, 5);
        $lecturer = $this->createMockUser('lecturer', 1, 5);
        $otherLecturer = $this->createMockUser('lecturer', 2, 8);

        $dept = new Department();
        $dept->id = 5;
        $dept->unit_id = 1;

        $this->assertTrue($policy->view($deptHead, $dept));
        $this->assertTrue($policy->view($lecturer, $dept));
        $this->assertFalse($policy->view($otherLecturer, $dept));
    }

    public function testScheduleNotePolicy(): void
    {
        $policy = new ScheduleNotePolicy();
        $user1 = $this->createMockUser('lecturer', 1);
        $user2 = $this->createMockUser('lecturer', 1);

        $noteOfUser1 = new ScheduleNote();
        $noteOfUser1->id = 1;
        $noteOfUser1->user_id = $user1->id;

        // Kendi notunu görebilir
        $this->assertTrue($policy->view($user1, $noteOfUser1));
        // Başkasının notunu göremez (eğer manager/admin değilse)
        $this->assertFalse($policy->view($user2, $noteOfUser1));

        // DTO ile oluşturma yetkisi
        $dtoOwn = new ScheduleNoteDTO(
            userId: $user1->id,
            academicYear: '2025 - 2026',
            semester: 'Güz',
            scheduleType: 'lesson',
            note: 'Test Not',
            id: null
        );
        $this->assertTrue($policy->create($user1, null, $dtoOwn));
    }

    public function testSchedulePolicyViewPublishedVsUnpublished(): void
    {
        $policy = new SchedulePolicy();
        $lecturer = $this->createMockUser('lecturer', 1, 1);

        $publishedSchedule = new Schedule();
        $publishedSchedule->id = 1;
        $publishedSchedule->owner_type = 'program';
        $publishedSchedule->owner_id = 999;
        $publishedSchedule->is_published = true;

        $unpublishedSchedule = new Schedule();
        $unpublishedSchedule->id = 2;
        $unpublishedSchedule->owner_type = 'program';
        $unpublishedSchedule->owner_id = 999;
        $unpublishedSchedule->is_published = false;

        // Yayınlanmış programı herkes görebilir
        $this->assertTrue($policy->view($lecturer, $publishedSchedule));
        $this->assertTrue($policy->view(null, $publishedSchedule));

        // Yayınlanmamış programı misafir göremez
        $this->assertFalse($policy->view(null, $unpublishedSchedule));
    }

    public function testClassroomScheduleViewAndPermissions(): void
    {
        $policy = new SchedulePolicy();
        $lecturer = $this->createMockUser('lecturer', 1, 1);

        $publishedClassroomSchedule = new Schedule();
        $publishedClassroomSchedule->id = 10;
        $publishedClassroomSchedule->owner_type = 'classroom';
        $publishedClassroomSchedule->owner_id = 50;
        $publishedClassroomSchedule->is_published = true;

        $unpublishedClassroomSchedule = new Schedule();
        $unpublishedClassroomSchedule->id = 11;
        $unpublishedClassroomSchedule->owner_type = 'classroom';
        $unpublishedClassroomSchedule->owner_id = 50;
        $unpublishedClassroomSchedule->is_published = false;

        // 1. Ziyaretçi (oturum açmamış / Home index) durumu:
        // Yayınlanmış sınıf takvimini görebilir
        $this->assertTrue($policy->view(null, $publishedClassroomSchedule));
        // Yayınlanmamış sınıf takvimini göremez
        $this->assertFalse($policy->view(null, $unpublishedClassroomSchedule));

        // 2. Sisteme giriş yapmış kullanıcı (akademisyen):
        // Taslak sınıf programını görebilir (müsaitlik/doluluk incelemesi için)
        $this->assertTrue($policy->view($lecturer, $unpublishedClassroomSchedule));

        // 3. Güncelleme (Update) yetkisi: Düz akademisyen sınıf takvimini güncelleyemez (güvenlik kontrolü)
        $this->assertFalse($policy->update($lecturer, $unpublishedClassroomSchedule));
    }
}
