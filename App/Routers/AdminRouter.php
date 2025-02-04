<?php
/**
 * Admin paneli sayfalarını yöneten dosya
 */

namespace App\Routers;

use App\Controllers\ClassroomController;
use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Controllers\ScheduleController;
use App\Controllers\UserController;
use App\Core\Router;
use Exception;

/**
 * AdminRouter Sınıfı
 * /admin altında gelen istekleri yönetir.
 */
class AdminRouter extends Router
{
    private $view_data = [];

    public function __construct()
    {
        // Her çağrıdan önce kullanıcı giriş durumunu kontrol et
        $this->beforeAction();
        $this->view_data["userController"] = new UserController();//her sayfada olmalı
        //todo yetki kontrolü
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
        $this->view_data = array_merge($this->view_data, [
            "departmentController" => new DepartmentController(),
            "classroomController" => new ClassroomController(),
            "lessonController" => new LessonController(),
            "programController" => new ProgramController(),
            "page_title" => "Anasayfa"]);
        $this->callView("admin/index", $this->view_data);
    }

    /*
     * User Routes
     */
    public function ListUsersAction()
    {
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Kullanıcı Listesi",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "programs" => (new ProgramController())->getProgramsList()]);
        $this->callView("admin/users/listusers", $this->view_data);
    }

    public function AddUserAction(int $department_id = null, int $program_id = null)
    {
        // todo bir program sayfasında yada bölüm sayfasında hoca ekle utonuna tıklandığında o bölüm ve program otomatik seçili gelmeli
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Kullanıcı Ekle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
        ]);
        $this->callView("admin/users/adduser", $this->view_data);
    }

    public function ProfileAction($id = null)
    {
        $userController = new UserController();
        if (is_null($id)) {
            $user = $userController->getCurrentUser();
        } else {
            $user = $userController->getUser($id);
        }
        $this->view_data = array_merge($this->view_data, [
            "user" => $user,
            "page_title" => $user->getFullName() . " Profil Sayfası",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "scheduleController" => new ScheduleController()]);
        $this->callView("admin/profile", $this->view_data);
    }

    public function EditUserAction($id = null)
    {
        $userController = new UserController();
        if (is_null($id)) {
            $user = $userController->getCurrentUser();
        } else {
            $user = $userController->getUser($id);
        }
        $this->view_data = array_merge($this->view_data, [
            "user" => $user,
            "page_title" => $user->getFullName() . " Kullanıcı Düzenle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "programController" => new ProgramController()]);
        $this->callView("admin/users/edituser", $this->view_data);
    }

    /*
     * Lesson Routes
     */
    public function lessonAction($id = null)
    {
        $lessonController = new LessonController();
        if (!is_null($id)) {
            $lesson = $lessonController->getLesson($id);
        } //todo else durumu ?
        $this->view_data = array_merge($this->view_data, [
            "lesson" => $lesson,
            "page_title" => $lesson->name . " Sayfası",
            "scheduleController" => new ScheduleController()]);
        $this->callView("admin/lessons/lesson", $this->view_data);
    }

    public function ListLessonsAction()
    {
        $this->view_data = array_merge($this->view_data, [
            "lessonController" => new LessonController(),
            "page_title" => "Ders Listesi"
        ]);
        $this->callView("admin/lessons/listlessons", $this->view_data);
    }

    public function AddLessonAction()
    {
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Ders Ekle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "lessonController" => new LessonController()]);
        $this->callView("admin/lessons/addlesson", $this->view_data);
    }

    public function EditLessonAction($id = null)
    {
        $lessonController = new LessonController();
        if (!is_null($id)) {
            $lesson = $lessonController->getLesson($id);
        } else {
            $lesson = null;//todo ders yoksa ne yapılmalı? hata mesajıyla sayfanın yinede yüklenmesi sağlanmalı
        }
        $this->view_data = array_merge($this->view_data, [
            "lessonController" => new LessonController(),
            "lesson" => $lesson,
            "page_title" => $lesson->getFullName() . " Düzenle",//todo ders olmayınca hata veriyor.
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "programController" => new ProgramController()]);
        $this->callView("admin/lessons/editlesson", $this->view_data);
    }

    /*
     * Classroom Routes
     */
    public function classroomAction($id = null)
    {
        try {
            $classroomController = new ClassroomController();
            if (!is_null($id)) {
                $classroom = $classroomController->getClassroom($id);
            } else throw new Exception("Derslik id Numarası belirtilmemiş");
            $this->view_data = array_merge($this->view_data, [
                "classroom" => $classroom,
                "page_title" => $classroom->name . " Sayfası"
            ]);
        } catch (Exception $exception) {
            $_SESSION["errors"] = [$exception->getMessage()];
        }
        $this->callView("admin/classrooms/classroom", $this->view_data);
    }

    public function ListClassroomsAction()
    {
        try {
            $classroomController = new ClassroomController();
            $this->view_data = array_merge($this->view_data, [
                "classroomController" => $classroomController,
                "classrooms" => $classroomController->getClassroomsList(),
                "page_title" => "Derslik Listesi"
            ]);
        } catch (Exception $exception) {
            $_SESSION["errors"] = [$exception->getMessage()];
        }
        $this->callView("admin/classrooms/listclassrooms", $this->view_data);
    }

    public function AddClassroomAction()
    {
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Derslik Ekle"
        ]);
        $this->callView("admin/classrooms/addclassroom", $this->view_data);
    }

    public function editClassroomAction($id = null)
    {
        try {
            $classroomController = new ClassroomController();
            if (!is_null($id)) {
                $classroom = $classroomController->getClassroom($id);
            } else {
                $classroom = null;//todo sınıf yoksa ne yapılmalı? hata mesajıyla sayfanın yinede yüklenmesi sağlanmalı
            }
            $this->view_data = array_merge($this->view_data, [
                "classroomController" => new ClassroomController(),
                "classroom" => $classroom,
                "page_title" => $classroom->name . "Düzenle",//todo ders olmayınca hata veriyor.
            ]);
        } catch (Exception $exception) {
            $_SESSION["errors"] = [$exception->getMessage()];
        }
        $this->callView("admin/classrooms/editclassroom", $this->view_data);
    }

    /*
     * Department Routes
     */
    public function departmentAction($id = null)
    {
        try {
            $departmentController = new DepartmentController();
            if (!is_null($id)) {
                $department = $departmentController->getDepartment($id);
            } else throw new Exception("İd belirtilmemiş");
            $this->view_data = array_merge($this->view_data, [
                "department" => $department,
                "page_title" => $department->name . " Sayfası"
            ]);
        } catch (Exception $exception) {
            $_SESSION["errors"] = [$exception->getMessage()];
        }
        $this->callView("admin/departments/department", $this->view_data);
    }

    public function ListDepartmentsAction()
    {
        $this->view_data = array_merge($this->view_data, [
            "departmentController" => new DepartmentController(),
            "page_title" => "Bölüm Listesi"
        ]);
        $this->callView("admin/departments/listdepartments", $this->view_data);
    }

    public function AddDepartmentAction()
    {
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Bölüm Ekle"
        ]);
        $this->callView("admin/departments/adddepartment", $this->view_data);
    }

    public function editDepartmentAction($id = null)
    {
        try {
            $departmentController = new DepartmentController();
            if (!is_null($id)) {
                $department = $departmentController->getDepartment($id);
            } else {
                $department = null;//todo sınıf yoksa ne yapılmalı? hata mesajıyla sayfanın yinede yüklenmesi sağlanmalı
            }
            $this->view_data = array_merge($this->view_data, [
                "departmentController" => $departmentController,
                "department" => $department,
                "page_title" => $department->name . " Düzenle",//todo ders olmayınca hata veriyor.
            ]);
        } catch (Exception $exception) {
            $_SESSION["errors"] = [$exception->getMessage()];
        }
        $this->callView("admin/departments/editdepartment", $this->view_data);
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
        $this->view_data = array_merge($this->view_data, [
            "program" => $program,
            "page_title" => $program->name . " Sayfası",
            "scheduleController" => new ScheduleController()
        ]);
        $this->callView("admin/programs/program", $this->view_data);
    }

    public function ListProgramsAction($department_id = null)
    {
        $this->view_data = array_merge($this->view_data, [
            "programController" => new ProgramController(),
            "page_title" => "Program Listesi",
            "department_id" => $department_id
        ]);
        $this->callView("admin/programs/listprograms", $this->view_data);
    }

    public function AddProgramAction($department_id = null)
    {
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Program Ekle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "department_id" => $department_id
        ]);
        $this->callView("admin/programs/addprogram", $this->view_data);
    }

    public function editProgramAction($id = null)
    {
        $programController = new ProgramController();
        if (!is_null($id)) {
            $program = $programController->getProgram($id);
        } else {
            $program = null;//todo sınıf yoksa ne yapılmalı? hata mesajıyla sayfanın yinede yüklenmesi sağlanmalı
        }
        $this->view_data = array_merge($this->view_data, [
            "programController" => $programController,
            "program" => $program,
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "page_title" => $program->name . " Düzenle",//todo ders olmayınca hata veriyor.
        ]);
        $this->callView("admin/programs/editprogram", $this->view_data);
    }

    /*
     * Takvim işlemleri
     */
    public function ShowscheduleAction()
    {
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Takvim ",
        ]);
        $this->callView("admin/schedules/showschedule", $this->view_data);
    }

    /**
     * @throws Exception
     */
    public function EditScheduleAction($department_id = null)
    {
        try {
            $userController = new UserController();
            $currentUser = $userController->getCurrentUser();
            $departmentController = new DepartmentController();
            if ($userController->canUserDoAction(8)) {
                $departments = $departmentController->getDepartmentsList();
            } elseif ($userController->canUserDoAction(7) and $currentUser->role == "department_head") {
                $departments = [$departmentController->getDepartment($currentUser->department_id)];
            } else {
                throw new Exception("Bu işlem için yetkiniz yok");
            }
            $this->view_data = array_merge($this->view_data, [
                "scheduleController" => new ScheduleController(),
                "departments" => $departments,
                "page_title" => "Takvim Düzenle",
            ]);
            $this->callView("admin/schedules/editschedule", $this->view_data);
        } catch (Exception $exception) {
            $_SESSION["errors"] = [$exception->getMessage()];
            header("location: /admin");
        }

    }
}