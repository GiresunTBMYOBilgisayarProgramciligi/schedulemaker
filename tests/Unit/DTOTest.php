<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\DTOs\BulkActionResultDTO;
use App\DTOs\LoginDTO;
use App\DTOs\SavePermissionsDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\DTOs\BuildingDTO;
use App\DTOs\ClassroomDTO;
use App\DTOs\DepartmentDTO;
use App\DTOs\ProgramDTO;
use App\DTOs\UnitDTO;
use App\DTOs\LessonDTO;
use App\DTOs\UserDTO;
use App\DTOs\ConflictFilterDTO;
use App\DTOs\AvailabilityFilterDTO;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;
use App\DTOs\CombineLessonDTO;
use App\DTOs\CombineExamLessonDTO;
use App\DTOs\DeleteCombineLessonDTO;
use App\DTOs\ToggleLockScheduleItemDTO;
use App\DTOs\DeleteScheduleResult;
use App\DTOs\SaveScheduleResult;
use App\Enums\ClassroomType;
use App\Enums\UnitType;
use App\Enums\UserRole;

class DTOTest extends BaseTestCase
{
    public function testBulkActionResultDTO(): void
    {
        $dto = new BulkActionResultDTO(success: [1, 2, 3], failed: [4 => 'Hata oluştu']);

        $this->assertEquals(3, $dto->getSuccessCount());
        $this->assertEquals(1, $dto->getFailedCount());
        $this->assertFalse($dto->isAllSuccessful());
        $this->assertFalse($dto->isAllFailed());
        $this->assertEquals(['success' => [1, 2, 3], 'failed' => [4 => 'Hata oluştu']], $dto->toArray());

        $successOnly = BulkActionResultDTO::successOnly([1, 2]);
        $this->assertTrue($successOnly->isAllSuccessful());
        $this->assertFalse($successOnly->isAllFailed());

        $failedOnly = BulkActionResultDTO::failureOnly([5 => 'Bulunamadı']);
        $this->assertTrue($failedOnly->isAllFailed());
        $this->assertFalse($failedOnly->isAllSuccessful());
    }

    public function testLoginDTO(): void
    {
        $dto = LoginDTO::fromArray([
            'mail'        => 'test@example.com',
            'password'    => 'secret123',
            'remember_me' => '1'
        ]);

        $this->assertEquals('test@example.com', $dto->mail);
        $this->assertEquals('secret123', $dto->password);
        $this->assertTrue($dto->rememberMe);
        $this->assertEquals([
            'mail'        => 'test@example.com',
            'password'    => 'secret123',
            'remember_me' => true,
        ], $dto->toArray());
    }

    public function testSavePermissionsDTO(): void
    {
        $dto = SavePermissionsDTO::fromArray([
            'user_id'     => '10',
            'scope'       => 'programs',
            'target_id'   => '5',
            'permissions' => json_encode(['view' => true, 'update' => false])
        ]);

        $this->assertEquals(10, $dto->userId);
        $this->assertEquals('programs', $dto->scope);
        $this->assertEquals(5, $dto->targetId);
        $this->assertEquals(['view' => true, 'update' => false], $dto->permissions);
    }

    public function testScheduleExportOptionsDTO(): void
    {
        $dto = ScheduleExportOptionsDTO::fromArray([
            'show_code'     => '0',
            'show_lecturer' => '1',
            'show_program'  => 'true',
            'show_observer' => false
        ]);

        $this->assertFalse($dto->showCode);
        $this->assertTrue($dto->showLecturer);
        $this->assertTrue($dto->showProgram);
        $this->assertFalse($dto->showObserver);
    }

    public function testBuildingDTO(): void
    {
        $dto = BuildingDTO::fromArray([
            'name'    => 'A Blok',
            'unit_id' => '2'
        ]);

        $this->assertEquals('A Blok', $dto->name);
        $this->assertEquals(2, $dto->unit_id);
        $this->assertEquals(['name' => 'A Blok', 'unit_id' => 2], $dto->toArray());
    }

    public function testClassroomDTO(): void
    {
        $dto = ClassroomDTO::fromArray([
            'name'        => 'D-101',
            'class_size'  => '40',
            'exam_size'   => '25',
            'building_id' => '1',
            'type'        => ClassroomType::CLASSROOM->value
        ]);

        $this->assertEquals('D-101', $dto->name);
        $this->assertEquals(40, $dto->class_size);
        $this->assertEquals(25, $dto->exam_size);
        $this->assertEquals(1, $dto->building_id);
        $this->assertEquals(ClassroomType::CLASSROOM, $dto->type);
    }

    public function testDepartmentDTO(): void
    {
        $dto = DepartmentDTO::fromArray([
            'name'           => 'Bilgisayar Teknolojileri',
            'chairperson_id' => '15',
            'unit_id'        => '3',
            'active'         => '1'
        ]);

        $this->assertEquals('Bilgisayar Teknolojileri', $dto->name);
        $this->assertEquals(15, $dto->chairperson_id);
        $this->assertEquals(3, $dto->unit_id);
        $this->assertTrue($dto->active);
    }

    public function testProgramDTO(): void
    {
        $dto = ProgramDTO::fromArray([
            'name'          => 'Bilgisayar Programcılığı',
            'department_id' => '4',
            'active'        => 'on'
        ]);

        $this->assertEquals('Bilgisayar Programcılığı', $dto->name);
        $this->assertEquals(4, $dto->department_id);
        $this->assertTrue($dto->active);
    }

    public function testUnitDTO(): void
    {
        $dto = UnitDTO::fromArray([
            'name'       => 'Teknik Bilimler MYO',
            'type'       => UnitType::Vocational->value,
            'manager_id' => '12',
            'active'     => true
        ]);

        $this->assertEquals('Teknik Bilimler MYO', $dto->name);
        $this->assertEquals(UnitType::Vocational, $dto->type);
        $this->assertEquals(12, $dto->manager_id);
        $this->assertTrue($dto->active);
        $this->assertEquals([
            'name'       => 'Teknik Bilimler MYO',
            'type'       => UnitType::Vocational->value,
            'manager_id' => 12,
            'active'     => true,
        ], $dto->toArray());
    }

    public function testLessonDTO(): void
    {
        $dto = LessonDTO::fromArray([
            'code'           => 'BBP101',
            'name'           => 'programlamaya giriş',
            'group_no'       => '1',
            'size'           => '30',
            'hours'          => '4',
            'type'           => '1',
            'semester_no'    => '1',
            'lecturer_id'    => '20',
            'department_id'  => '2',
            'program_id'     => '3',
            'semester'       => 'Güz',
            'classroom_type' => '1',
            'academic_year'  => '2025 - 2026',
            'building_id'    => '1'
        ]);

        $this->assertEquals('BBP101', $dto->code);
        $this->assertEquals(1, $dto->group_no);
        $this->assertEquals('Programlamaya Giriş', $dto->name);
        $this->assertEquals(30, $dto->size);
        $this->assertEquals(4, $dto->hours);
        $this->assertEquals(20, $dto->lecturer_id);
    }

    public function testUserDTO(): void
    {
        $dto = UserDTO::fromArray([
            'name'          => 'Ahmet',
            'last_name'     => 'Yılmaz',
            'mail'          => 'ahmet@example.com',
            'role'          => UserRole::Lecturer->value,
            'password'      => 'secretPass123',
            'department_id' => '2',
            'program_id'    => '3',
            'unit_id'       => '1'
        ]);

        $this->assertEquals('Ahmet', $dto->name);
        $this->assertEquals('Yılmaz', $dto->lastName);
        $this->assertEquals('ahmet@example.com', $dto->mail);
        $this->assertEquals(UserRole::Lecturer, $dto->role);
    }

    public function testConflictFilterDTO(): void
    {
        $dto = ConflictFilterDTO::fromArray([
            'day_index'   => '2',
            'week_index'  => '0',
            'start_time'  => '09:00',
            'end_time'    => '10:50',
            'type'        => 'lesson',
            'assignments' => [['owner_type' => 'user', 'owner_id' => 5]],
            'items'       => '[{"lesson_id":1}]'
        ]);

        $this->assertEquals(2, $dto->day_index);
        $this->assertEquals('09:00', $dto->start_time);
        $this->assertEquals('10:50', $dto->end_time);
        $this->assertEquals('lesson', $dto->type);
        $this->assertCount(1, $dto->assignments);
    }

    public function testAvailabilityFilterDTO(): void
    {
        $dto = AvailabilityFilterDTO::fromArray([
            'day_index'   => '1',
            'start_time'  => '13:00',
            'end_time'    => '14:50',
            'type'        => 'lesson',
            'schedule_id' => '100',
            'lesson_id'   => '50'
        ]);

        $this->assertEquals(1, $dto->day_index);
        $this->assertEquals('13:00', $dto->start_time);
        $this->assertEquals(100, $dto->schedule_id);
        $this->assertEquals(50, $dto->lesson_id);
    }

    public function testCombineLessonDTO(): void
    {
        $dto = CombineLessonDTO::fromArray([
            'parent_lesson_id' => '10',
            'child_lesson_id'  => '20',
            'items_to_remove'  => ['606_1', '606_2']
        ]);

        $this->assertEquals(10, $dto->parentId);
        $this->assertEquals(20, $dto->childId);
        $this->assertEquals([606 => [1, 2]], $dto->getParsedItemsToRemove());
    }

    public function testDeleteScheduleResult(): void
    {
        $result = DeleteScheduleResult::success([1, 2, 3], [10, 11]);

        $this->assertTrue($result->success);
        $this->assertEquals([1, 2, 3], $result->deletedIds);
        $this->assertEquals([10, 11], $result->createdIds);
        $this->assertEquals(3, $result->totalDeleted);
        $this->assertEquals(2, $result->totalCreated);

        $failure = DeleteScheduleResult::failure('Kayıt bulunamadı');
        $this->assertFalse($failure->success);
        $this->assertEquals(['Kayıt bulunamadı'], $failure->errors);
    }
}
