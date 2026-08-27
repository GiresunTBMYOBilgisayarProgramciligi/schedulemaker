<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Validators\LessonValidator;
use App\Validators\SettingsValidator;
use App\Validators\BulkActionValidator;
use App\Validators\CombineLessonValidator;
use App\Validators\CombineExamLessonValidator;
use App\Validators\DeleteCombineLessonValidator;
use App\Validators\Auth\ForgotPasswordValidator;
use App\Validators\Auth\ResetPasswordValidator;
use App\Exceptions\ValidationException;
use App\DTOs\LessonDTO;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;
use App\DTOs\CombineLessonDTO;
use App\DTOs\ForgotPasswordDTO;
use App\DTOs\ResetPasswordDTO;

class LessonAndActionValidatorsTest extends BaseTestCase
{
    public function testLessonValidatorValidData(): void
    {
        $validator = new LessonValidator();
        $data = [
            'code' => 'BIL101',
            'name' => 'Algoritmalar',
            'group_no' => 1,
            'size' => 40,
            'hours' => 3,
            'type' => 1,
            'semester_no' => 1,
            'lecturer_id' => 5,
            'department_id' => 2,
            'program_id' => 1,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025 - 2026',
            'building_id' => 1
        ];

        $dto = $validator->getDTO($data);
        $this->assertInstanceOf(LessonDTO::class, $dto);
        $this->assertEquals('BIL101', $dto->code);
        $this->assertEquals('Algoritmalar', $dto->name);
        $this->assertEquals(3, $dto->hours);
    }

    public function testLessonValidatorLecturerSelfUpdateAllowsMissingRelations(): void
    {
        $validator = new LessonValidator(isLecturerSelfUpdate: true);
        $data = [
            'code' => 'BIL101',
            'name' => 'Algoritmalar',
            'group_no' => 1,
            'size' => 40,
            'hours' => 3,
            'type' => 1,
            'semester_no' => 1,
            'semester' => 'Güz',
            'classroom_type' => 1,
            'academic_year' => '2025 - 2026',
            'building_id' => 1
        ];

        $dto = $validator->getDTO($data);
        $this->assertInstanceOf(LessonDTO::class, $dto);
    }

    public function testLessonValidatorInvalidDataThrowsException(): void
    {
        $validator = new LessonValidator();
        $this->expectException(ValidationException::class);
        $validator->validate(['name' => '', 'code' => '']);
    }

    public function testBulkActionValidatorDelete(): void
    {
        $validator = new BulkActionValidator();
        $dto = $validator->getDeleteDTO(['ids' => ['1', '2', '3']]);

        $this->assertInstanceOf(BulkDeleteDTO::class, $dto);
        $this->assertEquals([1, 2, 3], $dto->ids);
    }

    public function testBulkActionValidatorUpdate(): void
    {
        $validator = new BulkActionValidator();
        $dto = $validator->getUpdateDTO([
            'ids' => ['1', '2'],
            'fields' => ['active' => '1', 'department_id' => '5']
        ], 'program');

        $this->assertInstanceOf(BulkUpdateDTO::class, $dto);
        $this->assertEquals([1, 2], $dto->ids);
        $this->assertEquals(1, $dto->fields['active']);
        $this->assertEquals(5, $dto->fields['department_id']);
    }

    public function testCombineLessonValidatorValid(): void
    {
        $validator = new CombineLessonValidator();
        $data = [
            'parent_lesson_id' => 10,
            'child_lesson_id' => 20,
            'items_to_remove' => ['100_1']
        ];

        $dto = $validator->getDTO($data);
        $this->assertInstanceOf(CombineLessonDTO::class, $dto);
        $this->assertEquals(10, $dto->parentId);
        $this->assertEquals(20, $dto->childId);
    }

    public function testForgotPasswordValidator(): void
    {
        $validator = new ForgotPasswordValidator();
        $dto = $validator->getDTO(['email' => 'user@example.com']);

        $this->assertInstanceOf(ForgotPasswordDTO::class, $dto);
        $this->assertEquals('user@example.com', $dto->email);

        $this->expectException(ValidationException::class);
        $validator->validate(['email' => 'invalid-email']);
    }

    public function testResetPasswordValidator(): void
    {
        $validator = new ResetPasswordValidator();
        $dto = $validator->getDTO([
            'email' => 'user@example.com',
            'token' => 'securetoken123',
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ]);

        $this->assertInstanceOf(ResetPasswordDTO::class, $dto);
        $this->assertEquals('user@example.com', $dto->email);
        $this->assertEquals('securetoken123', $dto->token);

        $this->expectException(ValidationException::class);
        $validator->validate([
            'email' => 'user@example.com',
            'token' => '',
            'password' => '123',
            'password_confirmation' => 'mismatch'
        ]);
    }
}
