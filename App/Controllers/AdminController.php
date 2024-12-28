<?php
/**
 * Admin paneli sayfalarını yöneten dosya
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Users;
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
        $users = new Users();
        $this->callView("admin/users", ["user_list" => $users->get_user_list()]);
    }

    public function create_new_user()
    {
        /*
            todo ajax kontrolü
            aplication controller parse url ile tüm urleleri bir controller ve view öğresine yönlendiriyor.
            bu nedenle bu işlem için form action adresi /admin/register olarak ayarlanırsa RegisterAction metodu çalışır.
            bu metod gelen bir ajax post verisi tesipt ederse gelen veriyi kontrol edip bu metodu çalıştırır. bu metodtan aldığı cevaba göre sayfaya hata ya da başarı mesakı ekleyerek
            /admin/register view ini çalıştırır.
        */
        $new_user = new Users();
        $data = $_POST['data'];
        $new_user->user_name = $data['user_name'];
        $new_user->name = $data['name'];
        $new_user->last_name = $data['last_name'];
        $new_user->mail = $data['mail'];
        $new_user->password = password_hash($data['password'], PASSWORD_BCRYPT);

    }
}