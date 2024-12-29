<?php
/**
 * Admin paneli sayfalarını yöneten dosya
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Models\UsersController;
use App\Models\Lecturer;

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
        $this->callView("admin/index", ["lecturer" => new Lecturer()]);
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
        $users = (new UsersController())->get_user_list();
        $this->callView("admin/users", ["user_list" => $users]);
    }
}