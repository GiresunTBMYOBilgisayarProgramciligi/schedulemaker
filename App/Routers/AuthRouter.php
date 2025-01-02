<?php

namespace App\Routers;

use App\Core\Router;

class AuthRouter extends Router
{
    public function LoginAction()
    {
        $this->callView("auth/login");
    }

    public function RegisterAction()
    {
        $this->callView("auth/register");
    }

    /**
     * @return void
     */
    public function LogoutAction()
    {
        // Tüm session verilerini temizle
        session_unset();

        // Oturumu tamamen sonlandır
        session_destroy();

        // Çerezleri sil (remember me varsa)
        if (isset($_COOKIE[$_ENV["COOKIE_KEY"]])) {
            setcookie($_ENV["COOKIE_KEY"], "", time() - 3600, "/", "", true, true);
        }

        // Ana sayfaya veya giriş sayfasına yönlendir
        header("Location: /auth/login");
        exit;
    }
}