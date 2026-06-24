<?php

namespace App\Middlewares;

use App\Models\User;
use Exception;

/**
 * Kimlik doğrulama süreçlerini yöneten Middleware.
 * Sisteme giriş yapılmamışsa login sayfasına yönlendirir.
 * Ayrıca mevcut kullanıcıya her yerden ulaşılmasını sağlar.
 */
class AuthMiddleware
{
    private static ?User $currentUser = null;
    private static bool $isResolved = false;

    /**
     * İsteği korur. Giriş yapılmamışsa yönlendirir.
     */
    public static function handle(): void
    {
        if (!self::check()) {
            // Giriş yapılmamışsa gidilmek istenen URL'yi kaydet (AJAX değilse)
            if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
                $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/admin';
            }
            header("Location: /auth/login");
            exit;
        }
    }

    /**
     * Oturum açmış kullanıcı var mı kontrol eder.
     * @return bool
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Mevcut oturum açmış kullanıcıyı döner.
     * @return User|null
     */
    public static function user(): ?User
    {
        if (self::$isResolved) {
            return self::$currentUser;
        }

        $id = $_SESSION[$_ENV["SESSION_KEY"]] ?? $_COOKIE[$_ENV["COOKIE_KEY"]] ?? null;
        
        if ($id) {
            try {
                // İlişkileri de yükleyerek (department, program, lessons) kullanıcıyı getir
                $user = (new User())->get()->where(['id' => $id])->with(['department', 'program', 'lessons'])->first();
                self::$currentUser = $user ?: null;
            } catch (Exception $e) {
                self::$currentUser = null;
            }
        }

        self::$isResolved = true;
        return self::$currentUser;
    }
}
