<?php

namespace App\Validators;

/**
 * Validation sonucunu temsil eden immutable sınıf
 */
readonly class ValidationResult
{
    /**
     * @param bool $isValid Validasyon başarılı mı?
     * @param array $errors Hata mesajları listesi
     */
    public function __construct(
        public bool $isValid,
        public array $errors = []
    ) {
    }

    /**
     * Başarılı validasyon sonucu oluşturur
     * @return self
     */
    public static function success(): self
    {
        return new self(true);
    }

    /**
     * Başarısız validasyon sonucu oluşturur
     * @param array $errors Hata mesajları
     * @return self
     */
    public static function failed(array $errors): self
    {
        return new self(false, $errors);
    }

    /**
     * Tek bir hata ile başarısız sonuç oluşturur
     * @param string $error Hata mesajı
     * @return self
     */
    public static function failedWithError(string $error): self
    {
        return new self(false, [$error]);
    }

    /**
     * Hatalar varsa string olarak döner
     * @param string $separator Ayırıcı karakter
     * @return string
     */
    public function getErrorsAsString(string $separator = ', '): string
    {
        return implode($separator, $this->errors);
    }
}
