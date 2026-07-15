<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\CombineExamLessonDTO;

class CombineExamLessonValidator extends BaseValidator
{
    /**
     * @param array $data Doğrulanacak veriler
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        if (empty($data['parent_lesson_id']) || !is_numeric($data['parent_lesson_id']) || $data['parent_lesson_id'] <= 0) {
            $errors['parent_lesson_id'] = 'Birleştirilecek üst ders belirtilmemiş veya geçersiz.';
        }

        if (empty($data['child_lesson_id']) || !is_numeric($data['child_lesson_id']) || $data['child_lesson_id'] <= 0) {
            $errors['child_lesson_id'] = 'Bağlanacak ders belirtilmemiş veya geçersiz.';
        }

        if (isset($data['semester']) && !in_array($data['semester'], ['Güz', 'Bahar', 'Yaz'])) {
            $errors['semester'] = 'Geçersiz dönem seçimi.';
        }

        if (isset($data['academic_year']) && !preg_match('/^\d{4}-\d{4}$/', $data['academic_year'])) {
            $errors['academic_year'] = 'Geçersiz akademik yıl formatı. Örn: 2023-2024';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return CombineExamLessonDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): CombineExamLessonDTO
    {
        $this->validate($data);
        return CombineExamLessonDTO::fromArray($data);
    }
}
