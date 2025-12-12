<?php

namespace App\Routers;

use App\Controllers\UserController;
use App\Core\Router;
use Exception;

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
        $this->Redirect("/");
    }

    public function ajaxloginAction()
    {
        try {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0
            ) {
                $loginData = $_POST;
                $usersController = new UserController();
                $usersController->login([
                    'mail' => $loginData['mail'],
                    'password' => $loginData['password'],
                    "remember_me" => isset($loginData['remember_me'])
                ]);

                $response = array(
                    "msg" => "Kullanıcı başarıyla Giriş yaptı.",
                    "redirect" => "/admin",
                    "status" => "success"
                );
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response);
            }

        } catch (Exception $e) {
            $response = [
                "msg" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
                "status" => "error"
            ];
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response);
        }
    }
}