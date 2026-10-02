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
            if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
                $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/admin';
                header("Location: /auth/login");
            } else {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['error' => 'Oturum süresi doldu veya yetkisiz erişim. Lütfen tekrar giriş yapın.']);
            }
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

        $sessionKey = $_ENV["SESSION_KEY"] ?? 'schedule_session';
        $cookieKey  = $_ENV["COOKIE_KEY"] ?? 'schedule_cookie_';

        $userId = null;
        if (!empty($_SESSION[$sessionKey])) {
            $userId = (int)$_SESSION[$sessionKey];
        }

        if ($userId) {
            try {
                // İlişkileri de yükleyerek (department, program, lessons) kullanıcıyı getir
                $user = (new User())->get()->where(['id' => $userId])->with(['department', 'program', 'lessons'])->first();
                self::$currentUser = $user ?: null;
            } catch (Exception $e) {
                self::$currentUser = null;
            }
        } elseif (!empty($_COOKIE[$cookieKey])) {
            self::$currentUser = self::resolveUserFromSignedCookie((string)$_COOKIE[$cookieKey]);
        }

        self::$isResolved = true;
        return self::$currentUser;
    }

    /**
     * İmzalı çerezden kullanıcıyı doğrular ve döndürür.
     *
     * @param string $cookieValue ID:HMAC formatında imzalı çerez değeri
     * @return User|null
     */
    public static function resolveUserFromSignedCookie(string $cookieValue): ?User
    {
        $parts = explode(':', $cookieValue, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$userIdStr, $hmac] = $parts;
        if (!is_numeric($userIdStr) || (int)$userIdStr <= 0) {
            return null;
        }

        $userId = (int)$userIdStr;
        try {
            $user = (new User())->get()->where(['id' => $userId])->with(['department', 'program', 'lessons'])->first();
            if (!$user) {
                return null;
            }

            $secret = $_ENV['APP_KEY'] ?? 'schedulemaker_app_secure_salt';
            $expectedHmac = hash_hmac('sha256', $userId . ':' . $user->password, $secret);

            if (hash_equals($expectedHmac, $hmac)) {
                return $user;
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }
}
