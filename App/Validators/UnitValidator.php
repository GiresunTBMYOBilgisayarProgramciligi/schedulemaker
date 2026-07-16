<?php

namespace App\Validators;

use App\DTOs\UnitDTO;
use App\Enums\UnitType;
use App\Exceptions\ValidationException;

class UnitValidator extends BaseValidator
{
    /**
     * Birim verilerini doğrular.
     *
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        if ($this->isEmpty($data['name'] ?? null)) {
            $errors['name'] = 'Birim adı zorunludur.';
        } elseif (!$this->hasValidLength($data['name'], 2, 100)) {
            $errors['name'] = 'Birim adı 2 ile 100 karakter arasında olmalıdır.';
        }

        if (empty($data['type'])) {
            $errors['type'] = 'Birim türü zorunludur.';
        } elseif (!UnitType::tryFrom($data['type'])) {
            $errors['type'] = 'Geçersiz birim türü seçildi.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * @param array $data
     * @return UnitDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): UnitDTO
    {
        $this->validate($data);
        return UnitDTO::fromArray($data);
    }
}
