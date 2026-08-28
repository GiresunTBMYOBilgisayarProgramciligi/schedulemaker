<?php

namespace App\Routers;

use App\Services\UserService;
use App\Attributes\AuthRequired;
use App\Middlewares\GuestMiddleware;
use App\Middlewares\AuthMiddleware;
use App\Core\Router;
use Exception;
use App\Validators\Auth\LoginValidator;

/**
 * AuthRouter Sınıfı
 * Kullanıcı giriş, kayıt ve çıkış işlemlerini yönetir.
 */
class AuthRouter extends Router
{
    public function __construct()
    {
        parent::__construct();
    }

    public function LoginAction()
    {
        GuestMiddleware::handle();
        $this->view_data["page_title"] = "Giriş Yap";
        $this->assetManager->loadPageAssets('loginpage');
        $this->callView("auth/login/index");
    }

    public function RegisterAction()
    {
        GuestMiddleware::handle();
        $this->callView("auth/register");
    }

    /**
     * @return void
     */
    #[AuthRequired]
    public function LogoutAction()
    {
        $user = AuthMiddleware::user();
        if ($user) {
            $this->logger()->info($user->getFullName() . " çıkış yaptı.", $this->logContext());
        }

        // Tüm session verilerini temizle
        session_unset();

        // Oturumu tamamen sonlandır
        session_destroy();

        // Çerezleri sil (remember me varsa)
        if (isset($_COOKIE[$_ENV["COOKIE_KEY"]])) {
            setcookie($_ENV["COOKIE_KEY"], "", [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        // Ana sayfaya veya giriş sayfasına yönlendir
        $this->Redirect("/", false);
    }

    public function ajaxloginAction()
    {
        try {
            if (
                isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0
            ) {
                $dto = (new LoginValidator())->getDTO($_POST);
                $userService = new UserService();
                $userService->login($dto);

                $redirect = "/admin";
                if (isset($_SESSION['redirect_url'])) {
                    $redirect = $_SESSION['redirect_url'];
                    unset($_SESSION['redirect_url']);
                }

                $response = array(
                    "msg"      => "Kullanıcı başarıyla Giriş yaptı. Yönlendiriliyor...",
                    "redirect" => $redirect,
                    "status"   => "success"
                );
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response);
            }

        } catch (Exception $e) {
            $response = [
                "msg"    => $e->getMessage(),
                "trace"  => $e->getTraceAsString(),
                "status" => "error"
            ];
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response);
        }
    }

    public function forgotpasswordAction()
    {
        GuestMiddleware::handle();
        $this->view_data["page_title"] = "Şifremi Unuttum";
        $this->assetManager->loadPageAssets('loginpage');
        $this->callView("auth/passwords/email");
    }

    public function resetpasswordAction()
    {
        GuestMiddleware::handle();
        $this->view_data["page_title"] = "Şifre Sıfırlama";
        $this->view_data["token"] = $_GET['token'] ?? '';
        $this->view_data["email"] = $_GET['email'] ?? '';
        
        $this->assetManager->loadPageAssets('loginpage');
        $this->callView("auth/passwords/reset");
    }
}
