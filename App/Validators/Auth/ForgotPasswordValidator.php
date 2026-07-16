<?php

namespace App\Validators\Auth;

use App\Validators\BaseValidator;
use App\Exceptions\ValidationException;
use App\DTOs\ForgotPasswordDTO;

class ForgotPasswordValidator extends BaseValidator
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

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return ForgotPasswordDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): ForgotPasswordDTO
    {
        $this->validate($data);
        return ForgotPasswordDTO::fromArray($data);
    }
}
