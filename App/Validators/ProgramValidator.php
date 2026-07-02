<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\ProgramDTO;

class ProgramValidator extends BaseValidator
{
    /**
     * Program verilerini doğrular
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
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return ProgramDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): ProgramDTO
    {
        $this->validate($data);
        return ProgramDTO::fromArray($data);
    }
}
