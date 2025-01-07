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
            "departmentController" => new DepartmentController(),
            "classroomController" => new ClassroomController(),
            "lessonController" => new LessonController(),
            "programController" => new ProgramController(),
            "page_title" => "Anasayfa"];
        $this->callView("admin/index", $view_data);
    }

    /*
     * User Routes
     */
    public function ListUsersAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Kullanıcı Listesi",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "programs" => (new ProgramController())->getProgramsList()];
        $this->callView("admin/users/listusers", $view_data);
    }

    public function AddUserAction(int $department_id, int $program_id)
    {
        // todo bir program sayfasında yada bölüm sayfasında hoca ekle utonuna tıklandığında o bölüm ve program otomatik seçili gelmeli
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Kullanıcı Ekle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
        ];
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
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "programs" => (new ProgramController())->getProgramsList()];
        $this->callView("admin/profile", $view_data);
    }
    public function EditUserAction($id = null)
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
            "page_title" => $user->getFullName() . " Kullanıcı Düzenle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "programController" => new ProgramController()];
        $this->callView("admin/users/edituser", $view_data);
    }

    /*
     * Lesson Routes
     */
    public function lessonAction($id = null)
    {
        $lessonController = new LessonController();
        if (!is_null($id)) {
            $lesson =$lessonController->getLesson($id);
        } //todo else durumu ?
        $view_data = [
            "userController" => new UserController(), //her sayfada olmalı
            "lesson" => $lesson,
            "page_title" => $lesson->name . " Sayfası"];
        $this->callView("admin/lessons/lesson", $view_data);
    }
    public function ListLessonsAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "lessonController" => new LessonController(),
            "page_title" => "Ders Listesi"
        ];
        $this->callView("admin/lessons/listlessons", $view_data);
    }

    public function AddLessonAction()
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Ders Ekle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "lessonController" => new LessonController()];
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
            "page_title" => $lesson->getFullName() . " Düzenle",//todo ders olmayınca hata veriyor.
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "programController" => new ProgramController()];
        $this->callView("admin/lessons/editlesson", $view_data);
    }

    /*
     * Classroom Routes
     */
    public function classroomAction($id = null)
    {
        $classroomController = new ClassroomController();
        if (!is_null($id)) {
            $classroom = $classroomController->getClassroom($id);
        } // todo else durumu ?
        $view_data = [
            "userController" => new UserController(), // Her sayfada olmalı
            "classroom" => $classroom,
            "page_title" => $classroom->name . " Sayfası"
        ];
        $this->callView("admin/classrooms/classroom", $view_data);
    }
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
            $classroom = $classroomController->getClassroom($id);
        } else {
            $classroom = null;//todo sınıf yoksa ne yapılmalı? hata mesajıyla sayfanın yinede yüklenmesi sağlanmalı
        }
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "classroomController" => new ClassroomController(),
            "classroom" => $classroom,
            "page_title" => $classroom->name . "Düzenle",//todo ders olmayınca hata veriyor.
        ];
        $this->callView("admin/classrooms/editclassroom", $view_data);
    }

    /*
     * Department Routes
     */
    public function departmentAction($id = null)
    {
        $departmentController = new DepartmentController();
        if (!is_null($id)) {
            $department = $departmentController->getDepartment($id);
        } // todo else durumu ?
        $view_data = [
            "userController" => new UserController(), // Her sayfada olmalı
            "department" => $department,
            "page_title" => $department->name . " Sayfası"
        ];
        $this->callView("admin/departments/department", $view_data);
    }
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
            "page_title" => $department->name . " Düzenle",//todo ders olmayınca hata veriyor.
        ];
        $this->callView("admin/departments/editdepartment", $view_data);
    }

    /*
     * Program Routes
     */
    public function programAction($id = null)
    {
        $programController = new ProgramController();
        if (!is_null($id)) {
            $program = $programController->getProgram($id);
        } // todo else durumu ?
        $view_data = [
            "userController" => new UserController(), // Her sayfada olmalı
            "program" => $program,
            "page_title" => $program->name . " Sayfası"
        ];
        $this->callView("admin/programs/program", $view_data);
    }
    public function ListProgramsAction($department_id = null)
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "programController" => new ProgramController(),
            "page_title" => "Program Listesi",
            "department_id" => $department_id
        ];
        $this->callView("admin/programs/listprograms", $view_data);
    }

    public function AddProgramAction($department_id = null)
    {
        $view_data = [
            "userController" => new UserController(),//her sayfada olmalı
            "page_title" => "Program Ekle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "department_id" => $department_id
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
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "page_title" => $program->name . " Düzenle",//todo ders olmayınca hata veriyor.
        ];
        $this->callView("admin/programs/editprogram", $view_data);
    }
}