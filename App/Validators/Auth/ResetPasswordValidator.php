<?php

namespace App\Validators\Auth;

use App\Validators\BaseValidator;
use App\Exceptions\ValidationException;
use App\DTOs\ResetPasswordDTO;

class ResetPasswordValidator extends BaseValidator
{
    /**
     * @param array $data Doğrulanacak veri
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        if ($this->isEmpty($data['email'] ?? null)) {
            $errors['email'] = 'E-posta alanı zorunludur.';
        } elseif (!$this->isValidEmail($data['email'])) {
            $errors['email'] = 'Geçerli bir e-posta adresi giriniz.';
        }

        if ($this->isEmpty($data['token'] ?? null)) {
            $errors['token'] = 'Geçersiz veya süresi dolmuş token.';
        }

        if ($this->isEmpty($data['password'] ?? null)) {
            $errors['password'] = 'Şifre alanı zorunludur.';
        } elseif (!$this->hasValidLength($data['password'], 6)) {
            $errors['password'] = 'Şifre en az 6 karakter olmalıdır.';
        }

        if ($this->isEmpty($data['password_confirmation'] ?? null)) {
            $errors['password_confirmation'] = 'Şifre tekrar alanı zorunludur.';
        } elseif (($data['password'] ?? null) !== ($data['password_confirmation'] ?? null)) {
            $errors['password_confirmation'] = 'Şifreler eşleşmiyor.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return ResetPasswordDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): ResetPasswordDTO
    {
        $this->validate($data);
        return ResetPasswordDTO::fromArray($data);
    }
}
