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
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Core\Logger;
use App\Core\Router;

use App\Models\User;
use Exception;
use function App\Helpers\getCurrentSemester;
use function App\Helpers\isAuthorized;

/**
 * AdminRouter Sınıfı
 * /admin altında gelen istekleri yönetir.
 */
class AdminRouter extends Router
{
    private $view_data = [];
    private User $currentUser;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        try {
            // Her çağrıdan önce kullanıcı giriş durumunu kontrol et
            $this->beforeAction();
            $this->view_data["userController"] = new UserController();//her sayfada olmalı
            $this->currentUser = $this->view_data["userController"]->getCurrentUser();
        } catch (Exception $e) {
            Logger::setExceptionLog($e);
            throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }

    }

    /**
     * Aksiyonlardan önce çalıştırılan kontrol mekanizması
     */
    private function beforeAction(): void
    {
        $userController = new UserController();
        if (!$userController->isLoggedIn()) {
            //todo goback ön tanımlı olarak false olursa daha iyi olur gibi. ön tanımlı true olduğu için login redirect çalışmıyordu
            $this->Redirect('/auth/login', false);
        }
    }

    /**
     *
     * @return void
     * @throws Exception
     */
    public function IndexAction(): void
    {
        try {
            $programController = new ProgramController();
            $this->view_data = array_merge($this->view_data, [
                "departmentController" => new DepartmentController(),
                "classroomController" => new ClassroomController(),
                "lessonController" => new LessonController(),
                "programController" => $programController,
                "programs" => $programController->getProgramsList(),
                "page_title" => "Anasayfa"]);
            $this->callView("admin/index", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
        }

    }

    /*
     * User Routes
     */
    public function ListUsersAction()
    {
        try {
            if (!isAuthorized("department_head")) {
                Logger::setErrorLog("Kullanıcı listesini görme yetkiniz yok");
                throw new Exception("Kullanıcı listesini görme yetkiniz yok");
            }

            //$userController =$this->view_data["userController"]; bu şekilde kullanınca otomatik tamamlama çalışmıyor
            $userController = new UserController();
            $this->view_data = array_merge($this->view_data, [
                "page_title" => "Kullanıcı Listesi",
                "departments" => (new DepartmentController())->getDepartmentsList()
            ]);
            if ($this->currentUser->role == "department_head") {
                $this->view_data['users'] = $userController->getListByFilters(['department_id' => $this->currentUser->department_id]);
            } else$this->view_data['users'] = $userController->getListByFilters();
            $this->callView("admin/users/listusers", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin");
        }

    }

    public function AddUserAction(int $department_id = null, int $program_id = null)
    {
        // todo bir program sayfasında yada bölüm sayfasında hoca ekle utonuna tıklandığında o bölüm ve program otomatik seçili gelmeli
        try {
            $departmentFilters = [];
            if ($department_id) {
                $department = (new DepartmentController())->getDepartment($department_id);
                if (!(isAuthorized("submanager") or $this->currentUser->id == $department->chairperson_id)) {
                    Logger::setErrorLog("Bu bölüme yeni kullanıcı ekleme yetkiniz yok");
                    throw new Exception("Bu bölüme yeni kullanıcı ekleme yetkiniz yok");
                }
                //$departmentFilters["id"] = $department_id; //todo otomatik seçim olmayında sadece tek bir bölüm gösterilmesinin çok anlamı yok
            }
            if ($program_id) {
                $program = (new ProgramController())->getProgram($program_id);
                if (!(isAuthorized("submanager") or $this->currentUser->id == $program->getDepartment()->chairperson_id)) {
                    Logger::setErrorLog("Bu programa yeni kullanıcı ekleme yetkiniz yok");
                    throw new Exception("Bu programa yeni kullanıcı ekleme yetkiniz yok");
                }
            }
            if (!($department or $program)) {
                if (!isAuthorized("submanager")) {
                    Logger::setErrorLog("Yeni kullanıcı ekleme yetkiniz yok");
                    throw new Exception("Yeni kullanıcı ekleme yetkiniz yok");
                }
            }
            $this->view_data = array_merge($this->view_data, [
                "page_title" => "Kullanıcı Ekle",
                "departments" => (new DepartmentController())->getDepartmentsList($departmentFilters),
            ]);

            $this->callView("admin/users/adduser", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect(null, true);
        }

    }

    public function ProfileAction($id = null)
    {
        try {
            $userController = new UserController();
            if (is_null($id)) {
                $user = $userController->getCurrentUser();
            } else {
                $user = $userController->getUser($id);
            }
            if (!isAuthorized("submanager", false, $user)) {
                Logger::setErrorLog("Bu profili görme yetkiniz yok");
                throw new Exception("Bu profili görme yetkiniz yok");
            }
            $this->view_data = array_merge($this->view_data, [
                "user" => $user,
                "page_title" => $user->getFullName() . " Profil Sayfası",
                "departments" => (new DepartmentController())->getDepartmentsList(),
                "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(['owner_type' => 'user', 'owner_id' => $user->id, 'type' => 'lesson'], true),
            ]);
            $this->callView("admin/profile", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect(null, true);
        }

    }

    public function EditUserAction($id = null)
    {
        try {
            $userController = new UserController();
            if (is_null($id)) {
                $user = $userController->getCurrentUser();
            } else {
                $user = $userController->getUser($id);
            }
            if (!isAuthorized("submanager", false, $user)) {
                Logger::setErrorLog("Kullanıcı düzenleme yetkiniz yok");
                throw new Exception("Kullanıcı düzenleme yetkiniz yok");
            }
            $this->view_data = array_merge($this->view_data, [
                "user" => $user,
                "page_title" => $user->getFullName() . " Kullanıcı Düzenle",
                "departments" => (new DepartmentController())->getDepartmentsList(),
                "programController" => new ProgramController()]);
            $this->callView("admin/users/edituser", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect(null, true);
        }

    }

    /*
     * Lesson Routes
     */
    public function lessonAction($id = null)
    {
        try {
            $lessonController = new LessonController();
            if (!is_null($id)) {
                $lesson = $lessonController->getLesson($id);
            } else {
                Logger::setErrorLog("Ders İd numarası belirtilmelidir");
                throw new Exception("Ders İd numarası belirtilmelidir");
            }
            if (!isAuthorized("submanager", false, $lesson)) {
                Logger::setErrorLog("Bu dersi görme yetkiniz yok");
                throw new Exception("Bu dersi görme yetkiniz yok");
            }
            $this->view_data = array_merge($this->view_data, [
                "lesson" => $lesson,
                "page_title" => $lesson->name . " Sayfası",
                "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(['owner_type' => 'lesson', 'owner_id' => $lesson->id, 'type' => 'lesson'], true),
            ]);
            $this->callView("admin/lessons/lesson", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin/listlessons");
        }

    }

    public function ListLessonsAction()
    {
        try {
            if (!isAuthorized("department_head")) {
                Logger::setErrorLog("Ders listesini görme yetkiniz yok");
                throw new Exception("Ders listesini görme yetkiniz yok");
            }

            $lessonController = new LessonController();
            $this->view_data = array_merge($this->view_data, [
                "lessonController" => $lessonController,
                "page_title" => "Ders Listesi"
            ]);
            if ($this->currentUser->role == "department_head") {
                $this->view_data['lessons'] = $lessonController->getListByFilters(['department_id' => $this->currentUser->department_id]);
            } else $this->view_data['lessons'] = $lessonController->getListByFilters();
            $this->callView("admin/lessons/listlessons", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin");
        }

    }

    public function AddLessonAction()
    {
        try {
            if (!isAuthorized("department_head")) {
                Logger::setErrorLog("Ders ekleme yetkiniz yok");
                throw new Exception("Ders ekleme yetkiniz yok");
            }
            $userController = new UserController();
            $this->view_data = array_merge($this->view_data, [
                "page_title" => "Ders Ekle",
                "departments" => (new DepartmentController())->getDepartmentsList(),
                "lessonController" => new LessonController(),
                "classroomTypes" => (new ClassroomController())->getTypeList()
            ]);
            if ($this->currentUser->role == "department_head") {
                $this->view_data['lecturers'] = $userController->getListByFilters(['department_id' => $this->currentUser->department_id]);
            } else $this->view_data['lecturers'] = $userController->getListByFilters();
            $this->callView("admin/lessons/addlesson", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin/listlessons");
        }
    }

    public function EditLessonAction($id = null): void
    {
        try {
            $lessonController = new LessonController();
            if (!is_null($id)) {
                $lesson = $lessonController->getLesson($id);
            } else {
                Logger::setErrorLog("Belirtilen ders bulunamadı");
                throw new Exception("Belirtilen ders bulunamadı");
            }
            if (!isAuthorized("submanager", false, $lesson)) {
                Logger::setErrorLog("Bu dersi düzenleme yetkiniz yok");
                throw new Exception("Bu dersi düzenleme yetkiniz yok");
            }

            $this->view_data = array_merge($this->view_data, [
                "lessonController" => new LessonController(),
                "lesson" => $lesson,
                "page_title" => $lesson->getFullName() . " Düzenle",
                "departments" => (new DepartmentController())->getDepartmentsList(),
                "programController" => new ProgramController(),
                "classroomTypes" => (new ClassroomController())->getTypeList()
            ]);
            $userController = new UserController();
            if ($this->currentUser->role == "department_head") {
                $this->view_data['lecturers'] = $userController->getListByFilters(['department_id' => $this->currentUser->department_id]);
                /* Bölümsüz hocalar için dersin hocası da listeye ekleniyor. (Okul dışından gelen hocalar için)*/
                $this->view_data['lecturers'][] = $userController->getUser($lesson->lecturer_id);
            } elseif ($this->currentUser->role == "lecturer") {
                $this->view_data['lecturers'][] = $userController->getUser($lesson->lecturer_id);
            } else$this->view_data['lecturers'] = $userController->getListByFilters();
            $this->callView("admin/lessons/editlesson", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect(null, true);
        }

    }

    /*
     * Classroom Routes
     */
    public function classroomAction($id = null)
    {
        try {
            if (!isAuthorized("submanager")) {
                Logger::setErrorLog("Derslik sayfasını görme yetkiniz yok");
                throw new Exception("Derslik sayfasını görme yetkiniz yok");
            }
            $classroomController = new ClassroomController();
            if (!is_null($id)) {
                $classroom = $classroomController->getClassroom($id);
            } else {
                Logger::setErrorLog("Derslik id Numarası belirtilmemiş");
                throw new Exception("Derslik id Numarası belirtilmemiş");
            }
            $this->view_data = array_merge($this->view_data, [
                "classroom" => $classroom,
                "page_title" => $classroom->name . " Sayfası",
                "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(['owner_type' => 'classroom', 'owner_id' => $classroom->id, 'type' => 'lesson'], true),
            ]);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect();
        }
        $this->callView("admin/classrooms/classroom", $this->view_data);
    }

    public function ListClassroomsAction()
    {
        try {
            if (!isAuthorized("submanager")) {
                Logger::setErrorLog("Derslik listesini görme yetkiniz yok");
                throw new Exception("Derslik listesini görme yetkiniz yok");
            }
            $classroomController = new ClassroomController();
            $this->view_data = array_merge($this->view_data, [
                "classroomController" => $classroomController,
                "classrooms" => $classroomController->getClassroomsList(),
                "page_title" => "Derslik Listesi"
            ]);
            $this->callView("admin/classrooms/listclassrooms", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect();
        }
    }

    public function AddClassroomAction()
    {
        try {
            if (!isAuthorized("submanager")) {
                Logger::setErrorLog("Yeni derslik ekleme yetkiniz yok");
                throw new Exception("Yeni derslik ekleme yetkiniz yok");
            }
            $classroomController = new ClassroomController();
            $this->view_data = array_merge($this->view_data, [
                "page_title" => "Derslik Ekle",
                "classroomTypes" => $classroomController->getTypeList()
            ]);
            $this->callView("admin/classrooms/addclassroom", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin");
        }
    }

    public function editClassroomAction($id = null)
    {
        try {
            if (!isAuthorized("submanager")) {
                Logger::setErrorLog("Bu dersliği düzenleme yetkiniz yok");
                throw new Exception("Bu dersliği düzenleme yetkiniz yok");
            }
            $classroomController = new ClassroomController();
            if (!is_null($id)) {
                $classroom = $classroomController->getClassroom($id);
            } else {
                Logger::setErrorLog("Derslik Bulunamadı");
                throw new Exception("Derslik Bulunamadı");
            }
            $this->view_data = array_merge($this->view_data, [
                "classroomController" => $classroomController,
                "classroom" => $classroom,
                "classroomTypes"=> $classroomController->getTypeList(),
                "page_title" => $classroom->name . "Düzenle",
            ]);
            $this->callView("admin/classrooms/editclassroom", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect();
        }
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
            } else {
                Logger::setErrorLog("İd belirtilmemiş");
                throw new Exception("İd belirtilmemiş");
            }
            if (!isAuthorized("submanager", false, $department)) {
                Logger::setErrorLog("Bu bölüm sayfasını görme yetkiniz yok");
                throw new Exception("Bu bölüm sayfasını görme yetkiniz yok");
            }
            $this->view_data = array_merge($this->view_data, [
                "department" => $department,
                "page_title" => $department->name . " Sayfası"
            ]);
            $this->callView("admin/departments/department", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect();
        }
    }

    public function ListDepartmentsAction()
    {
        try {
            if (!isAuthorized("submanager")) {
                Logger::setErrorLog("Bölümler listesini görmek için yetkiniz yok");
                throw new Exception("Bölümler listesini görmek için yetkiniz yok");
            }
            $departmentController = new DepartmentController();
            $this->view_data = array_merge($this->view_data, [
                "departmentController" => $departmentController,
                "departments" => $departmentController->getDepartmentsList(),
                "page_title" => "Bölüm Listesi"
            ]);
            $this->callView("admin/departments/listdepartments", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect(null, true);
        }
    }

    public function AddDepartmentAction()
    {
        try {
            if (!isAuthorized("submanager")) {
                Logger::setErrorLog("Yeni Bölüm ekleme yetkiniz yok");
                throw new Exception("Yeni Bölüm ekleme yetkiniz yok");
            }
            $this->view_data = array_merge($this->view_data, [
                "page_title" => "Bölüm Ekle",
                "lecturers" => $this->view_data["userController"]->getLecturerList(),
            ]);
            $this->callView("admin/departments/adddepartment", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin");
        }

    }

    public function editDepartmentAction($id = null)
    {
        try {
            if (!isAuthorized("submanager")) {
                Logger::setErrorLog("Bu bölümü düzenleme yetkiniz yok");
                throw new Exception("Bu bölümü düzenleme yetkiniz yok");
            }
            $departmentController = new DepartmentController();
            if (!is_null($id)) {
                $department = $departmentController->getDepartment($id);
            } else {
                Logger::setErrorLog("Bölüm bulunamadı");
                throw new Exception("Bölüm bulunamadı");
            }
            $this->view_data = array_merge($this->view_data, [
                "departmentController" => $departmentController,
                "department" => $department,
                "page_title" => $department->name . " Düzenle",//todo ders olmayınca hata veriyor.
                "lecturers" => $this->view_data["userController"]->getLecturerList(),
            ]);
            $this->callView("admin/departments/editdepartment", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect();
        }
    }

    /*
     * Program Routes
     */
    public function programAction($id = null)
    {
        try {
            $programController = new ProgramController();
            if (!is_null($id)) {
                $program = $programController->getProgram($id);
                if (!$program) {
                    Logger::setErrorLog("Belirtilen Program bulunamadı");
                    throw new Exception("Belirtilen Program bulunamadı");
                }
            } else{
                Logger::setErrorLog("Program id değeri belirtilmelidir");
                throw new Exception("Program id değeri belirtilmelidir");
            }
            if (!isAuthorized("submanager", false, $program)){
                Logger::setErrorLog("Bu programı görüntülemek için yetkiniz yok");
                throw new Exception("Bu programı görüntülemek için yetkiniz yok");
            }
            $this->view_data = array_merge($this->view_data, [
                "program" => $program,
                "page_title" => $program->name . " Sayfası",
                "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(['owner_type' => 'program', 'owner_id' => $program->id, 'type' => 'lesson'], true),
            ]);
            $this->callView("admin/programs/program", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin/listprograms");
        }
    }

    /**
     * @param $department_id
     * @return void
     */
    public function ListProgramsAction($department_id = null): void
    {
        try {
            if (!isAuthorized("submanager")){
                Logger::setErrorLog("Programlar listesini görmek için yetkiniz yok");
                throw new Exception("Programlar listesini görmek için yetkiniz yok");
            }
            $programController = new ProgramController();
            $this->view_data = array_merge($this->view_data, [
                "programController" => $programController,
                "programs" => $programController->getProgramsList($department_id),
                "page_title" => "Program Listesi",
            ]);
            $this->callView("admin/programs/listprograms", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin");
        }

    }

    public function AddProgramAction($department_id = null)
    {
        try {
            if (!isAuthorized("submanager")){
                Logger::setErrorLog("Program ekleme yetkiniz yok");
                throw new Exception("Program ekleme yetkiniz yok");
            }
            $this->view_data = array_merge($this->view_data, [
                "page_title" => "Program Ekle",
                "departments" => (new DepartmentController())->getDepartmentsList(),
                "department_id" => $department_id
            ]);
            $this->callView("admin/programs/addprogram", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin/listprograms");
        }

    }

    public function editProgramAction($id = null)
    {
        try {
            if (!isAuthorized("submanager")){
                Logger::setErrorLog("Program düzenleme yetkiniz yok");
                throw new Exception("Program düzenleme yetkiniz yok");
            }
            $programController = new ProgramController();
            if (!is_null($id)) {
                $program = $programController->getProgram($id);
                if (!$program) {
                    Logger::setErrorLog("Belirtilen Program bulunamadı");
                    throw new Exception("Belirtilen Program bulunamadı");
                }
            } else {
                Logger::setErrorLog("Program id numarası belirtilmelidir");
                throw new Exception("Program id numarası belirtilmelidir");
            }
            $this->view_data = array_merge($this->view_data, [
                "programController" => $programController,
                "program" => $program,
                "departments" => (new DepartmentController())->getDepartmentsList(),
                "page_title" => $program->name . " Düzenle",//todo ders olmayınca hata veriyor.
            ]);
            $this->callView("admin/programs/editprogram", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect("/admin/listprograms");
        }

    }

    /*
     * Takvim işlemleri
     */

    /**
     * @throws Exception
     */
    public function EditScheduleAction($department_id = null)
    {
        try {
            if (!isAuthorized("department_head")){
                Logger::setErrorLog("Ders programı düzenleme yetkiniz yok");
                throw new Exception("Ders programı düzenleme yetkiniz yok");
            }
            $userController = new UserController();
            $currentUser = $userController->getCurrentUser();
            $departmentController = new DepartmentController();
            $settingsController = new SettingsController();
            if ($userController->canUserDoAction(8)) {
                $departments = $departmentController->getDepartmentsList();
            } elseif ($userController->canUserDoAction(7) and $currentUser->role == "department_head") {
                $departments = [$departmentController->getDepartment($currentUser->department_id)];
            } else {
                Logger::setErrorLog("Bu işlem için yetkiniz yok");
                throw new Exception("Bu işlem için yetkiniz yok");
            }
            $this->view_data = array_merge($this->view_data, [
                "scheduleController" => new ScheduleController(),
                "departments" => $departments,
                "settings" => $settingsController->getSettings(),
                "page_title" => "Takvim Düzenle",
                "current_semesters" => getCurrentSemester()
            ]);
            $this->callView("admin/schedules/editschedule", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect();
        }
    }

    /*
     * Ayarlar
     */
    public function SettingsAction()
    {
        try {
            if (!isAuthorized("submanager")){
                Logger::setErrorLog("Ayarlar sayfasına erişim yetkiniz yok");
                throw new Exception("Ayarlar sayfasına erişim yetkiniz yok");
            }
            $settingsController = new SettingsController();
            $this->view_data = array_merge($this->view_data, [
                "page_title" => "Ayarlar",
                "settings" => $settingsController->getSettings()
            ]);
            $this->callView("admin/settings/settings", $this->view_data);
        } catch (Exception $e) {
            Logger::setAndShowErrorLog($e->getMessage());
            $this->Redirect();
        }
    }
}