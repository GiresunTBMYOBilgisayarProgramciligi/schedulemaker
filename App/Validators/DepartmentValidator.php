<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\DepartmentDTO;

class DepartmentValidator extends BaseValidator
{
    /**
     * Bölüm verilerini doğrular
     *
     * @param array $data Doğrulanacak veriler
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        // Ad doğrulaması
        if ($this->isEmpty($data['name'] ?? null)) {
            $errors['name'] = 'Bölüm adı zorunludur.';
        } elseif (!$this->hasValidLength($data['name'], 2, 100)) {
            $errors['name'] = 'Bölüm adı 2 ile 100 karakter arasında olmalıdır.';
        }

        // Başkan doğrulaması (Opsiyonel)
        if (!empty($data['chairperson_id']) && !is_numeric($data['chairperson_id'])) {
            $errors['chairperson_id'] = 'Bölüm başkanı ID değeri sayısal olmalıdır.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return DepartmentDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): DepartmentDTO
    {
        $this->validate($data);
        return DepartmentDTO::fromArray($data);
    }
}
