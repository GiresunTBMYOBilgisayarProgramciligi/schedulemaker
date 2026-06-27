<?php

namespace App\Validators;

class DepartmentValidator extends BaseValidator
{
    /**
     * Bölüm verilerini doğrular
     *
     * @param array $data Doğrulanacak veriler
     * @return ValidationResult
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        // Ad doğrulaması
        if (empty($data['name'])) {
            $errors[] = 'Bölüm adı zorunludur.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Bölüm adı en fazla 255 karakter olabilir.';
        }

        // Başkan doğrulaması (Opsiyonel)
        if (!empty($data['chairperson_id']) && !is_numeric($data['chairperson_id'])) {
            $errors[] = 'Bölüm başkanı ID değeri sayısal olmalıdır.';
        }

        if (!empty($errors)) {
            return ValidationResult::failed($errors);
        }

        return ValidationResult::success();
    }
}
