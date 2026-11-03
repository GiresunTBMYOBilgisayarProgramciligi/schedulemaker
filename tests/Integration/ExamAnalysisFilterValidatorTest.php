<?php

namespace Tests\Integration;

use App\Enums\ExamType;
use App\Exceptions\ValidationException;
use App\Validators\Schedule\ExamAnalysisFilterValidator;
use Tests\BaseTestCase;
use function App\Helpers\getSettingValue;

class ExamAnalysisFilterValidatorTest extends BaseTestCase
{
    private ExamAnalysisFilterValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ExamAnalysisFilterValidator();
    }

    public function testDefaultsAreAssignedFromSettingsAndMidterm(): void
    {
        $dto = $this->validator->getDTO([]);

        $this->assertSame((string) getSettingValue('semester'), $dto->semester);
        $this->assertSame((string) getSettingValue('academic_year'), $dto->academic_year);
        $this->assertSame(ExamType::MIDTERM, $dto->examType());
        $this->assertNull($dto->unit_id);
        $this->assertNull($dto->department_id);
    }

    public function testEmptyFormValuesFallBackToDefaults(): void
    {
        // Filtre formu boş alanları da gönderir
        $dto = $this->validator->getDTO(['semester' => '', 'academic_year' => '', 'exam_type' => '', 'unit_id' => '', 'department_id' => '']);

        $this->assertSame((string) getSettingValue('semester'), $dto->semester);
        $this->assertSame(ExamType::MIDTERM, $dto->examType());
        $this->assertNull($dto->unit_id);
    }

    public function testExplicitPeriodAndFiltersAreKept(): void
    {
        $dto = $this->validator->getDTO([
            'semester' => 'Bahar', 'academic_year' => '2024 - 2025', 'exam_type' => 'but', 'unit_id' => '3', 'department_id' => '7',
        ]);

        $this->assertSame('Bahar', $dto->semester);
        $this->assertSame('2024 - 2025', $dto->academic_year);
        $this->assertSame(ExamType::MAKEUP, $dto->examType());
        $this->assertSame(3, $dto->unit_id);
        $this->assertSame(7, $dto->department_id);
    }

    public function testInvalidValuesThrowValidationException(): void
    {
        foreach ([['semester' => 'Kış'], ['academic_year' => '2026'], ['exam_type' => 'quiz']] as $data) {
            try {
                $this->validator->getDTO($data);
                $this->fail('ValidationException bekleniyordu: ' . json_encode($data));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(array_key_first($data), $e->getValidationErrors());
            }
        }
    }
}
