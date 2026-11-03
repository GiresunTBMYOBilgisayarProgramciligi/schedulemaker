<?php

namespace App\Validators\Schedule;

use App\DTOs\ExamAnalysisFilterDTO;
use App\Enums\ExamType;
use App\Exceptions\ValidationException;

/**
 * Sınav analizleri sayfası filtre doğrulayıcı
 *
 * Dönem ve akademik yıl gelmezse sistem ayarlarındaki değerler, sınav tipi gelmezse vize varsayılan olarak atanır.
 * Sınav tipi kısa adlarla (vize, final, but) de verilebilir; DTO'da ExamType değerine çevrilir.
 *
 * Desteklenen operasyonlar:
 * - examAnalysis: Sınav analizleri sayfası filtreleri
 */
class ExamAnalysisFilterValidator extends BaseScheduleFilterValidator
{
    private const SEMESTERS = ['Güz', 'Bahar', 'Yaz'];

    protected function getOperationRules(): array
    {
        return [
            'examAnalysis' => [
                'required' => [],
                'optional' => ['unit_id', 'department_id'],
                'defaults' => ['semester', 'academic_year', 'exam_type'],
            ],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function getDTO(array $data, string $operation = 'examAnalysis'): ExamAnalysisFilterDTO
    {
        $sanitized = $this->sanitize($data, $operation);

        $errors = [];
        if (!in_array($sanitized['semester'], self::SEMESTERS, true)) {
            $errors['semester'] = "Geçersiz dönem: {$sanitized['semester']}";
        }
        if (!preg_match('/^\d{4} - \d{4}$/', (string) $sanitized['academic_year'])) {
            $errors['academic_year'] = "Geçersiz akademik yıl: {$sanitized['academic_year']}";
        }
        $examType = ExamType::fromAlias((string) $sanitized['exam_type']);
        if (!$examType) {
            $errors['exam_type'] = "Geçersiz sınav tipi: {$sanitized['exam_type']}";
        }
        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası', $errors);
        }

        $sanitized['exam_type'] = $examType->value;

        return ExamAnalysisFilterDTO::fromArray($sanitized);
    }

    protected function getDefaultValue(string $key): mixed
    {
        return match ($key) {
            'exam_type' => ExamType::MIDTERM->value,
            default => parent::getDefaultValue($key),
        };
    }
}
