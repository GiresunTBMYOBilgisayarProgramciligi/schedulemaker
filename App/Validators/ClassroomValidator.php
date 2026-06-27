<?php

namespace App\Validators;

use App\Enums\ClassroomType;

class ClassroomValidator extends BaseValidator
{
    /**
     * Derslik verilerini doğrular
     *
     * @param array $data Doğrulanacak veriler
     * @return ValidationResult
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        // Ad doğrulaması
        if (empty($data['name'])) {
            $errors[] = 'Derslik adı zorunludur.';
        } elseif (mb_strlen($data['name']) > 100) {
            $errors[] = 'Derslik adı en fazla 100 karakter olabilir.';
        }

        // Kapasite doğrulaması
        if (isset($data['class_size']) && !is_numeric($data['class_size'])) {
            $errors[] = 'Derslik kapasitesi sayısal bir değer olmalıdır.';
        }

        // Sınav kapasitesi doğrulaması
        if (isset($data['exam_size']) && !is_numeric($data['exam_size'])) {
            $errors[] = 'Sınav kapasitesi sayısal bir değer olmalıdır.';
        }

        // Tür doğrulaması
        if (empty($data['type'])) {
            $errors[] = 'Derslik türü zorunludur.';
        } elseif (!ClassroomType::tryFrom((int)$data['type'])) {
            $errors[] = 'Geçersiz derslik türü seçildi.';
        }

        if (!empty($errors)) {
            return ValidationResult::failed($errors);
        }

        return ValidationResult::success();
    }
}
