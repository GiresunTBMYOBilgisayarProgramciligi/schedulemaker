<?php
/**
 * Admin paneli sayfalarını yöneten dosya
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Models\UsersController;

/**
 * AdminController Sınıfı
 * /admin altında gelen istekleri yönetir.
 */
class AdminController extends Controller
{
    public function __construct()
    {
        // Her çağrıdan önce kullanıcı giriş durumunu kontrol et
        $this->beforeAction();

    }

    /**
     * Aksiyonlardan önce çalıştırılan kontrol mekanizması
     */
    private function beforeAction(): void
    {
        $usersController = new UsersController();
        if (!$usersController->isLoggedIn()) {
            $this->Redirect('/auth/login');
        }
    }

    /**
     *
     * @return void
     */
    public function IndexAction(): void
    {
        $view_data = [
            "usersController" => new UsersController(),
            "page_title" => "Anasayfa"];
        $this->callView("admin/index", $view_data);
    }

    public function UsersAction()
    {
        $view_data = [
            "usersController" => new UsersController(),
            "page_title" => "Kullanıcı İşlemleri"];
        $this->callView("admin/users", $view_data);
    }

    public function ProfileAction($id = null)
    {
        $usersController = new UsersController();
        if (is_null($id)){
            $user= $usersController->getCurrentUser();
        }
        else{
            $user= $usersController->getUser($id);
        }
        $view_data = [
            "usersController" => $usersController,
            "user" => $user,
            "page_title" => "Profil"];
        $this->callView("admin/profile", $view_data);
    }
}