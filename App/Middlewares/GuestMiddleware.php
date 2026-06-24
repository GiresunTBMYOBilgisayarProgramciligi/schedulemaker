<?php

namespace App\Middlewares;

/**
 * Giriş yapmamış misafir kullanıcılar için Middleware.
 * Giriş yapmış bir kullanıcı login veya register sayfasına gitmek isterse
 * onu admin paneline yönlendirir.
 */
class GuestMiddleware
{
    /**
     * İsteği korur. Giriş yapılmışsa ana sayfaya yönlendirir.
     */
    public static function handle(): void
    {
        if (AuthMiddleware::check()) {
            // Zaten giriş yapmış kullanıcı
            header("Location: /admin");
            exit;
        }
    }
}
