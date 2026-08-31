<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Validators\ToggleLockScheduleItemValidator;
use App\Validators\Schedule\ScheduleExportFilterValidator;
use App\Validators\Schedule\ScheduleMutationFilterValidator;
use App\Validators\Schedule\ScheduleViewFilterValidator;
use App\Exceptions\ValidationException;
use App\DTOs\ToggleLockScheduleItemDTO;
use App\DTOs\ScheduleExportFilterDTO;

class ScheduleValidatorsTest extends BaseTestCase
{
    public function testToggleLockScheduleItemValidator(): void
    {
        $validator = new ToggleLockScheduleItemValidator();

        $dto = $validator->getDTO(['ids' => [1, 2, 3]]);
        $this->assertInstanceOf(ToggleLockScheduleItemDTO::class, $dto);
        $this->assertEquals([1, 2, 3], $dto->ids);

        $dtoSingle = $validator->getDTO(['id' => 5]);
        $this->assertEquals([5], $dtoSingle->ids);

        $this->expectException(ValidationException::class);
        $validator->validate(['ids' => []]);
    }

    public function testScheduleExportFilterValidator(): void
    {
        $validator = new ScheduleExportFilterValidator();

        $dto = $validator->getDTO([
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => 1,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ], 'exportSchedule');

        $this->assertInstanceOf(ScheduleExportFilterDTO::class, $dto);
        $this->assertEquals('lesson', $dto->type);
        $this->assertEquals('program', $dto->owner_type);
        $this->assertEquals(1, $dto->owner_id);
    }

    public function testScheduleViewFilterValidatorSanitize(): void
    {
        $validator = new ScheduleViewFilterValidator();

        $sanitized = $validator->sanitize([
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => '10',
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ], 'getSchedulesHTML');

        $this->assertEquals('lesson', $sanitized['type']);
        $this->assertEquals('program', $sanitized['owner_type']);
        $this->assertEquals(10, $sanitized['owner_id']);
    }

    public function testScheduleViewFilterValidatorWithSemesterNo(): void
    {
        $validator = new ScheduleViewFilterValidator();

        $dto = $validator->getDTO([
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => 10,
            'semester_no' => '3',
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ], 'getSchedulesHTML');

        $this->assertEquals('lesson', $dto->type);
        $this->assertEquals('program', $dto->owner_type);
        $this->assertEquals(10, $dto->owner_id);
        $this->assertEquals(3, $dto->semester_no);
    }

    public function testScheduleMutationFilterValidatorMissingFieldThrowsException(): void
    {
        $validator = new ScheduleMutationFilterValidator();

        $this->expectException(ValidationException::class);
        $validator->validate([], 'deleteSchedule');
    }
}
