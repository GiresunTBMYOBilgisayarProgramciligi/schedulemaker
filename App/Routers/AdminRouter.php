<?php
/**
 * Admin paneli sayfalarını yöneten dosya
 */

namespace App\Routers;

use App\Controllers\ClassroomController;
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
            "page_title" => $lesson->getFullName(). "Düzenle" ,//todo ders olmayınca hata veriyor.
            "departments" => (new DepartmentController())->getDepartments(),
            "programs" => (new ProgramController())->getPrograms()];
        $this->callView("admin/lessons/editlesson", $view_data);
    }
    /*
     * Classroom Routes
     */
    public function ListClassroomsAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "classroomController" => new ClassroomController(),
            "page_title" => "Derslik Listesi"
        ];
        $this->callView("admin/classrooms/listclassrooms", $view_data);
    }
    public function AddClassroomAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Derslik Ekle"
            ];
        $this->callView("admin/classrooms/addclassroom", $view_data);
    }
    public function editClassroomAction($id = null)
    {
        $classroomController = new ClassroomController();
        if (!is_null($id)) {
            $classroom = $classroomController->getClass($id);
        } else {
            $classroom = null;//todo sınıf yoksa ne yapılmalı? hata mesajıyla sayfanın yinede yüklenmesi sağlanmalı
        }
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "classroomController" => new ClassroomController(),
            "classroom" => $classroom,
            "page_title" => $classroom->name. "Düzenle" ,//todo ders olmayınca hata veriyor.
            ];
        $this->callView("admin/classrooms/editclassroom", $view_data);
    }
    /*
     * Department Routes
     */
    public function ListDepartmentsAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "departmentController" => new DepartmentController(),
            "page_title" => "Bölüm Listesi"
        ];
        $this->callView("admin/departments/listdepartments", $view_data);
    }
    public function AddDepartmentAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "departmentController" => new DepartmentController(),
            "page_title" => "Bölüm Ekle"
        ];
        $this->callView("admin/departments/adddepartment", $view_data);
    }
    public function editDepartmentAction($id = null)
    {
        $departmentController = new DepartmentController();
        if (!is_null($id)) {
            $department = $departmentController->getDepartment($id);
        } else {
            $department = null;//todo sınıf yoksa ne yapılmalı? hata mesajıyla sayfanın yinede yüklenmesi sağlanmalı
        }
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "departmentController" => $departmentController,
            "department" => $department,
            "page_title" => $department->name. "Düzenle" ,//todo ders olmayınca hata veriyor.
        ];
        $this->callView("admin/departments/editdepartment", $view_data);
    }
    /*
     * Program Routes
     */
    public function ListProgramsAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "programController" => new ProgramController(),
            "page_title" => "Program Listesi"
        ];
        $this->callView("admin/programs/listprograms", $view_data);
    }
    public function AddProgramAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "programController" => new ProgramController(),
            "page_title" => "Program Ekle"
        ];
        $this->callView("admin/programs/addprogram", $view_data);
    }
    public function editProgramAction($id = null)
    {
        $programController = new ProgramController();
        if (!is_null($id)) {
            $program = $programController->getProgram($id);
        } else {
            $program = null;//todo sınıf yoksa ne yapılmalı? hata mesajıyla sayfanın yinede yüklenmesi sağlanmalı
        }
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "programController" => $programController,
            "program" => $program,
            "page_title" => $program->name. "Düzenle" ,//todo ders olmayınca hata veriyor.
        ];
        $this->callView("admin/programs/editprogram", $view_data);
    }
}