<?php
/**
 * Admin paneli sayfalarını yöneten dosya
 */

namespace App\Routers;

use App\Core\Router;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\UsersController;

/**
 * AdminRouter Sınıfı
 * /admin altında gelen istekleri yönetir.
 */
class AdminRouter extends Router
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

    public function UsersListAction()
    {
        $view_data = [
            "usersController" => new UsersController(),
            "page_title" => "Kullanıcı Listesi",
            "departments" => (new Department())->getDepartments(),
            "programs" => (new Program())->getPrograms()];
        $this->callView("admin/users/userslist", $view_data);
    }
    public function AddUserAction()
    {
        $view_data = [
            "usersController" => new UsersController(),
            "page_title" => "Kullanıcı Ekle",
            "departments" => (new Department())->getDepartments(),
            "programs" => (new Program())->getPrograms()];
        $this->callView("admin/users/adduser", $view_data);
    }

    public function ProfileAction($id = null)
    {
        $usersController = new UsersController();
        if (is_null($id)) {
            $user = $usersController->getCurrentUser();
        } else {
            $user = $usersController->getUser($id);
        }
        $view_data = [
            "usersController" => $usersController,
            "user" => $user,
            "page_title" => $user->getFullName() . " Profil Sayfası",
            "departments" => (new Department())->getDepartments(),
            "programs" => (new Program())->getPrograms()];
        $this->callView("admin/profile", $view_data);
    }

    public function LessonsListAction()
    {
        $view_data = [
            "usersController" => new UsersController(),
            "lessons" => (new Lesson())->getLessons(),
            "page_title" => "Ders Listesi"
        ];
        $this->callView("admin/lessons/lessonslist", $view_data);
    }
    public function classroomsAction()
    {
        $view_data = [
            "usersController" => new UsersController(),
            "page_title" => "Derslik İşlemleri"
        ];
        $this->callView("admin/clasrooms", $view_data);
    }
    public function departmentsAction()
    {
        $view_data = [
            "usersController" => new UsersController(),
            "page_title" => "Bölüm İşlemleri"
        ];
        $this->callView("admin/departments", $view_data);
    }
}