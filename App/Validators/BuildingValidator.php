<?php

namespace App\Validators;

use App\DTOs\BuildingDTO;
use App\Exceptions\ValidationException;

class BuildingValidator extends BaseValidator
{
    /**
     * Bina verilerini doğrular.
     *
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        if ($this->isEmpty($data['name'] ?? null)) {
            $errors['name'] = 'Bina adı zorunludur.';
        } elseif (!$this->hasValidLength($data['name'], 2, 100)) {
            $errors['name'] = 'Bina adı 2 ile 100 karakter arasında olmalıdır.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * @param array $data
     * @return BuildingDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): BuildingDTO
    {
        $this->validate($data);
        return BuildingDTO::fromArray($data);
    }
}
