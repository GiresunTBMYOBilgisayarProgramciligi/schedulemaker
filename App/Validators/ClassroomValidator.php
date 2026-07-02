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
