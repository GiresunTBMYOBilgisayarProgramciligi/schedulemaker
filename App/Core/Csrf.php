<?php

namespace App\Core;

/**
 * CSRF (Cross-Site Request Forgery) koruma servisi.
 *
 * Oturum tabanlı kriptografik olarak güvenli CSRF belirteci (token) üretir,
 * saklar ve doğrular.
 */
class Csrf
{
    public const SESSION_KEY = '_csrf_token';
    public const TOKEN_NAME = '_csrf_token';
    public const HEADER_NAME = 'X-CSRF-TOKEN';

    /**
     * Test veya özel durumlar için CSRF denetiminin devre dışı bırakılabilmesini sağlar.
     */
    public static bool $enabled = true;

    /**
     * Oturumu başlatır (başlatılmamışsa).
     */
    protected static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    /**
     * Oturum için CSRF token üretir veya mevcut olanı döndürür.
     */
    public static function generateToken(): string
    {
        self::ensureSessionStarted();

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Geçerli CSRF token'ı döndürür.
     */
    public static function getToken(): string
    {
        return self::generateToken();
    }

    /**
     * CSRF token'ı sıfırlar ve yeni bir token döndürür.
     */
    public static function refreshToken(): string
    {
        self::ensureSessionStarted();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Verilen token'ın oturumdaki token ile eşleşip eşleşmediğini zamanlama güvenli (timing-safe) kontrol eder.
     */
    public static function validate(?string $token): bool
    {
        self::ensureSessionStarted();

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        if (empty($token) || !is_string($token)) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * HTML formları için gizli CSRF input alanını üretir.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . $token . '">';
    }

    /**
     * Token değerini döndürür (helper alias).
     */
    public static function token(): string
    {
        return self::getToken();
    }
}
