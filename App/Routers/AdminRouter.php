<?php
/**
 * Admin paneli sayfalarını yöneten dosya
 */

namespace App\Routers;

use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Core\Router;

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
        $userController = new UserController();
        if (!$userController->isLoggedIn()) {
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
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Anasayfa"];
        $this->callView("admin/index", $view_data);
    }
    /*
     * User Routes
     */
    public function UsersListAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Kullanıcı Listesi",
            "departments" => (new DepartmentController())->getDepartments(),
            "programs" => (new ProgramController())->getPrograms()];
        $this->callView("admin/users/userslist", $view_data);
    }
    public function AddUserAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Kullanıcı Ekle",
            "departments" => (new DepartmentController())->getDepartments(),
            "programs" => (new ProgramController())->getPrograms()];
        $this->callView("admin/users/adduser", $view_data);
    }
    public function ProfileAction($id = null)
    {
        $userController = new UserController();
        if (is_null($id)) {
            $user = $userController->getCurrentUser();
        } else {
            $user = $userController->getUser($id);
        }
        $view_data = [
            "userController" => $userController, //her sayfada olmalı
            "user" => $user,
            "page_title" => $user->getFullName() . " Profil Sayfası",
            "departments" => (new DepartmentController())->getDepartments(),
            "programs" => (new ProgramController())->getPrograms()];
        $this->callView("admin/profile", $view_data);
    }
    /*
     * Lesson Routes
     */
    public function LessonsListAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "lessonController" => new LessonController(),
            "page_title" => "Ders Listesi"
        ];
        $this->callView("admin/lessons/lessonslist", $view_data);
    }
    public function AddLessonAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Ders Ekle",
            "departments" => (new DepartmentController())->getDepartments(),
            "programs" => (new ProgramController())->getPrograms()];
        $this->callView("admin/lessons/addlesson", $view_data);
    }
    public function editLessonAction($id = null)
    {
        $lessonController = new LessonController();
        if (!is_null($id)) {
            $lesson = $lessonController->getLesson($id);
        } else {
            $lesson = null;//todo ders yoksa ne yapılmalı? hata mesajıyla sayfanın yinede yüklenmesi sağlanmalı
        }
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "lessonController" => new LessonController(),
            "lesson" => $lesson,
            "page_title" => $lesson->getFullName() ,//todo ders olmayınca hata veriyor.
            "departments" => (new DepartmentController())->getDepartments(),
            "programs" => (new ProgramController())->getPrograms()];
        $this->callView("admin/lessons/lesson", $view_data);
    }
    /*
     * Classroom Routes
     */
    public function classroomsAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Derslik İşlemleri"
        ];
        $this->callView("admin/clasrooms", $view_data);
    }
    /*
     * Department Routes
     */
    public function departmentsAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Bölüm İşlemleri"
        ];
        $this->callView("admin/departments", $view_data);
    }
}