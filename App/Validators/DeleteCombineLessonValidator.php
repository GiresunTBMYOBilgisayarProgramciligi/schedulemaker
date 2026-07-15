<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\DeleteCombineLessonDTO;

class DeleteCombineLessonValidator extends BaseValidator
{
    public function validate(array $data): void
    {
        $errors = [];

        if (empty($data['id']) || !is_numeric($data['id']) || $data['id'] <= 0) {
            $errors['id'] = 'Geçersiz ID.';
        }

        if (isset($data['type']) && !in_array($data['type'], ['lesson', 'exam'])) {
            $errors['type'] = 'Geçersiz tür.';
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

    public function getDTO(array $data): DeleteCombineLessonDTO
    {
        $this->validate($data);
        return DeleteCombineLessonDTO::fromArray($data);
    }
}
