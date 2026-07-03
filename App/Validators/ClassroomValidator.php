<?php

namespace App\Validators;

use App\Enums\ClassroomType;

use App\Exceptions\ValidationException;
use App\DTOs\ClassroomDTO;

class ClassroomValidator extends BaseValidator
{
    /**
     * Derslik verilerini doğrular
     *
     * @param array $data Doğrulanacak veriler
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        // Ad doğrulaması
        if (empty($data['name'])) {
            $errors['name'] = 'Derslik adı zorunludur.';
        } elseif (mb_strlen($data['name']) > 50) {
            $errors['name'] = 'Derslik adı en fazla 50 karakter olabilir.';
        }

        // Kapasite doğrulaması
        if (isset($data['class_size']) && !is_numeric($data['class_size'])) {
            $errors['class_size'] = 'Derslik kapasitesi sayısal bir değer olmalıdır.';
        }

        // Sınav kapasitesi doğrulaması
        if (isset($data['exam_size']) && !is_numeric($data['exam_size'])) {
            $errors['exam_size'] = 'Sınav kapasitesi sayısal bir değer olmalıdır.';
        }

        // Tür doğrulaması
        if (empty($data['type'])) {
            $errors['type'] = 'Derslik türü zorunludur.';
        } elseif (!ClassroomType::tryFrom((int)$data['type'])) {
            $errors['type'] = 'Geçersiz derslik türü seçildi.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return ClassroomDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): ClassroomDTO
    {
        $this->validate($data);
        return ClassroomDTO::fromArray($data);
    }
}
