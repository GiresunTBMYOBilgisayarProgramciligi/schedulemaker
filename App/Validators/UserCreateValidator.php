<?php

namespace App\Validators;

use App\Enums\UserRole;
use App\Enums\UserTitle;

/**
 * Yeni kullanıcı oluşturma (veya güncelleme) isteklerini doğrulayan sınıf.
 */
class UserCreateValidator extends BaseValidator
{
    /**
     * @param array $data Doğrulanacak veri (POST verisi)
     * @return ValidationResult
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        // İsim Kontrolü
        if ($this->isEmpty($data['name'] ?? null)) {
            $errors[] = 'İsim alanı zorunludur.';
        } elseif (!$this->hasValidLength($data['name'], 2, 50)) {
            $errors[] = 'İsim 2 ile 50 karakter arasında olmalıdır.';
        }

        // Soyisim Kontrolü
        if ($this->isEmpty($data['last_name'] ?? null)) {
            $errors[] = 'Soyisim alanı zorunludur.';
        } elseif (!$this->hasValidLength($data['last_name'], 2, 50)) {
            $errors[] = 'Soyisim 2 ile 50 karakter arasında olmalıdır.';
        }

        // Email Kontrolü
        if ($this->isEmpty($data['mail'] ?? null)) {
            $errors[] = 'E-posta alanı zorunludur.';
        } elseif (!$this->isValidEmail($data['mail'])) {
            $errors[] = 'Geçerli bir e-posta adresi giriniz.';
        }

        // Şifre Kontrolü (Sadece gönderilmişse doğrula, boş bırakılırsa varsayılan atanır)
        if (!empty($data['password']) && !$this->hasValidLength($data['password'], 6)) {
            $errors[] = 'Şifre en az 6 karakter olmalıdır.';
        }

        // Role Kontrolü (Enum Tip Güvenliği)
        if ($this->isEmpty($data['role'] ?? null)) {
            $errors[] = 'Kullanıcı rolü seçimi zorunludur.';
        } elseif (!UserRole::tryFrom($data['role'])) {
            $errors[] = 'Geçersiz kullanıcı rolü seçimi.';
        }

        // Ünvan Kontrolü (Enum Tip Güvenliği)
        // Ünvan boş olabilir (öğrenciler veya ünvanı olmayanlar için)
        if (!empty($data['title']) && !UserTitle::tryFrom($data['title'])) {
            $errors[] = 'Geçersiz akademik ünvan seçimi.';
        }

        if (!empty($errors)) {
            return ValidationResult::failed($errors);
        }

        return ValidationResult::success();
    }
}
