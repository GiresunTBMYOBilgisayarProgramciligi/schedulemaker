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
    /**
     *
     * @return void
     */
    public function IndexAction(): void
    {
        //todo if not login redirct to login page
        $this->callView("admin/index", ["usersController" => new UsersController()]);
    }

    public function LoginAction()
    {
        $this->callView("admin/login");
    }

    public function RegisterAction()
    {
        $this->callView("admin/register");
    }

    public function UsersAction()
    {
        $usersController = new UsersController();
        $this->callView("admin/users", ["userController" => $usersController]);
    }
}