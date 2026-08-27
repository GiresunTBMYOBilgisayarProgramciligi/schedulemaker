<?php

namespace App\Validators\Auth;

use App\Validators\BaseValidator;
use App\Exceptions\ValidationException;
use App\DTOs\LoginDTO;

class LoginValidator extends BaseValidator
{
    /**
     * @param array $data Doğrulanacak veri
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        if ($this->isEmpty($data['mail'] ?? null)) {
            $errors['mail'] = 'E-posta alanı zorunludur.';
        } elseif (!$this->isValidEmail($data['mail'])) {
            $errors['mail'] = 'Geçerli bir e-posta adresi giriniz.';
        }

        if ($this->isEmpty($data['password'] ?? null)) {
            $errors['password'] = 'Şifre alanı zorunludur.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Giriş bilgileri geçersiz.', $errors);
        }
    }

    /**
     * @param array $data
     * @return LoginDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): LoginDTO
    {
        $this->validate($data);
        return LoginDTO::fromArray($data);
    }
}
