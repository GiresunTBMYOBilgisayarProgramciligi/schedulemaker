<?php

namespace App\Validators;

/**
 * Tüm validator sınıfları için temel sınıf
 * 
 * Her validator, veri doğrulama işlemlerini gerçekleştirir
 * ve ValidationResult döner
 */
abstract class BaseValidator
{
    /**
     * Veriyi doğrular ve sonuç döner
     * @param array $data Doğrulanacak veri
     * @return ValidationResult
     */
    abstract public function validate(array $data): ValidationResult;

    /**
     * Zaman formatını kontrol eder (HH:MM)
     * @param string $time
     * @return bool
     */
    protected function isValidTimeFormat(string $time): bool
    {
        return (bool) preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }

    /**
     * Tarih formatını kontrol eder (YYYY-MM-DD)
     * @param string $date
     * @return bool
     */
    protected function isValidDateFormat(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }

    /**
     * Email formatını kontrol eder
     * @param string $email
     * @return bool
     */
    protected function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Değerin boş olup olmadığını kontrol eder
     * @param mixed $value
     * @return bool
     */
    protected function isEmpty(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Değerin belirtilen aralıkta olup olmadığını kontrol eder
     * @param int|float $value
     * @param int|float $min
     * @param int|float $max
     * @return bool
     */
    protected function isInRange(int|float $value, int|float $min, int|float $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * Değerin belirtilen uzunlukta olup olmadığını kontrol eder
     * @param string $value
     * @param int $min
     * @param int|null $max
     * @return bool
     */
    protected function hasValidLength(string $value, int $min, ?int $max = null): bool
    {
        $length = mb_strlen($value);

        if ($length < $min) {
            return false;
        }

        if ($max !== null && $length > $max) {
            return false;
        }

        return true;
    }
}
