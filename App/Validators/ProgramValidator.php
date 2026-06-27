<?php

namespace App\Validators;

class ProgramValidator extends BaseValidator
{
    /**
     * Program verilerini doğrular
     *
     * @param array $data Doğrulanacak veriler
     * @return ValidationResult
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        // Ad doğrulaması
        if (empty($data['name'])) {
            $errors[] = 'Program adı zorunludur.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Program adı en fazla 255 karakter olabilir.';
        }

        // Bölüm ID doğrulaması
        if (empty($data['department_id'])) {
            $errors[] = 'Bölüm seçimi zorunludur.';
        } elseif (!is_numeric($data['department_id'])) {
            $errors[] = 'Bölüm değeri geçerli bir sayı olmalıdır.';
        }

        if (!empty($errors)) {
            return ValidationResult::failed($errors);
        }

        return ValidationResult::success();
    }
}
