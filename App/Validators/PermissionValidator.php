<?php

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\DTOs\SavePermissionsDTO;

class PermissionValidator extends BaseValidator
{
    /**
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        if ($this->isEmpty($data['user_id'] ?? null) || !is_numeric($data['user_id'])) {
            $errors['user_id'] = 'Geçerli bir kullanıcı ID belirtilmelidir.';
        }

        if ($this->isEmpty($data['scope'] ?? null)) {
            $errors['scope'] = 'Yetki kapsamı (scope) belirtilmelidir.';
        }

        if ($this->isEmpty($data['target_id'] ?? null) || !is_numeric($data['target_id'])) {
            $errors['target_id'] = 'Geçerli bir hedef ID belirtilmelidir.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Yetki verileri geçersiz.', $errors);
        }
    }

    /**
     * @param array $data
     * @return SavePermissionsDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): SavePermissionsDTO
    {
        $this->validate($data);
        return SavePermissionsDTO::fromArray($data);
    }
}
