<?php

namespace App\Validators;

use App\Enums\UserRole;
use App\Enums\UserTitle;

use App\Exceptions\ValidationException;
use App\DTOs\UserDTO;

/**
 * Kullanıcı oluşturma ve güncelleme isteklerini doğrulayan sınıf.
 */
class UserValidator extends BaseValidator
{
    /**
     * @param array $data Doğrulanacak veri (POST verisi)
     * @return void
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $errors = [];

        // İsim Kontrolü
        if ($this->isEmpty($data['name'] ?? null)) {
            $errors['name'] = 'İsim alanı zorunludur.';
        } elseif (!$this->hasValidLength($data['name'], 2, 50)) {
            $errors['name'] = 'İsim 2 ile 50 karakter arasında olmalıdır.';
        }

        // Soyisim Kontrolü
        if ($this->isEmpty($data['last_name'] ?? null)) {
            $errors['last_name'] = 'Soyisim alanı zorunludur.';
        } elseif (!$this->hasValidLength($data['last_name'], 2, 50)) {
            $errors['last_name'] = 'Soyisim 2 ile 50 karakter arasında olmalıdır.';
        }

        // Email Kontrolü
        if ($this->isEmpty($data['mail'] ?? null)) {
            $errors['mail'] = 'E-posta alanı zorunludur.';
        } elseif (!$this->isValidEmail($data['mail'])) {
            $errors['mail'] = 'Geçerli bir e-posta adresi giriniz.';
        }

        // Şifre Kontrolü (Sadece gönderilmişse doğrula, boş bırakılırsa varsayılan atanır)
        if (!empty($data['password']) && !$this->hasValidLength($data['password'], 6)) {
            $errors['password'] = 'Şifre en az 6 karakter olmalıdır.';
        }

        // Role Kontrolü (Enum Tip Güvenliği)
        if ($this->isEmpty($data['role'] ?? null)) {
            $errors['role'] = 'Kullanıcı rolü seçimi zorunludur.';
        } elseif (!UserRole::tryFrom($data['role'])) {
            $errors['role'] = 'Geçersiz kullanıcı rolü seçimi.';
        }

        // Ünvan Kontrolü (Enum Tip Güvenliği)
        // Ünvan boş olabilir (öğrenciler veya ünvanı olmayanlar için)
        if (!empty($data['title']) && !UserTitle::tryFrom($data['title'])) {
            $errors['title'] = 'Geçersiz akademik ünvan seçimi.';
        }

        // Üst Birim (Unit) Kontrolü
        if (!empty($data['unit_id']) && !is_numeric($data['unit_id'])) {
            $errors['unit_id'] = 'Üst birim ID değeri sayısal olmalıdır.';
        }

        if (!empty($errors)) {
            throw new ValidationException('Veri doğrulama hatası.', $errors);
        }
    }

    /**
     * Veriyi doğrular ve DTO nesnesi döndürür.
     * @param array $data
     * @return UserDTO
     * @throws ValidationException
     */
    public function getDTO(array $data): UserDTO
    {
        $this->validate($data);
        return UserDTO::fromArray($data);
    }
}
