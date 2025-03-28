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
use App\Core\AssetManager;
use App\Core\Router;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\User;
use Exception;
use function App\Helpers\getCurrentSemester;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\isAuthorized;

/**
 * AdminRouter Sınıfı
 * /admin altında gelen istekleri yönetir.
 */
class AdminRouter extends Router
{
    private $view_data = [];
    private User $currentUser;
    private AssetManager $assetManager;

    public function __construct()
    {
        $this->beforeAction();
        $this->assetManager = new AssetManager();
        $this->view_data["userController"] = new UserController();
        $this->view_data["assetManager"] = $this->assetManager; // View'da kullanmak için
        $this->currentUser = $this->view_data["userController"]->getCurrentUser();
    }

    /**
     * Aksiyonlardan önce çalıştırılan kontrol mekanizması
     * @throws Exception
     */
    private function beforeAction(): void
    {
        $userController = new UserController();
        $this->view_data['currentUser'] = $userController->getCurrentUser();
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
        $programController = new ProgramController();
        $user = (new UserController())->getCurrentUser();
        $this->view_data = array_merge($this->view_data, [
            "departmentController" => new DepartmentController(),
            "classroomController" => new ClassroomController(),
            "lessonController" => new LessonController(),
            "programController" => $programController,
            "programs" => $programController->getProgramsList(),
            "page_title" => "Anasayfa"]);
        if (!is_null($user->program_id))
            $this->view_data["scheduleHTML"] = (new ScheduleController())->getSchedulesHTML(['owner_type' => 'program', 'owner_id' => $user->program_id, 'type' => 'lesson'], true);
        else
            $this->view_data["scheduleHTML"] = (new ScheduleController())->getSchedulesHTML(['owner_type' => 'user', 'owner_id' => $user->id, 'type' => 'lesson'], true);
        $this->callView("admin/index", $this->view_data);
    }

    /*
     * User Routes
     */
    public function ListUsersAction()
    {
        if (!isAuthorized("department_head")) {
            throw new Exception("Kullanıcı listesini görme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('listpages');
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
    }

    /**
     * @throws Exception
     */
    public function AddUserAction(int $department_id = null, int $program_id = null)
    {
        // todo bir program sayfasında yada bölüm sayfasında hoca ekle utonuna tıklandığında o bölüm ve program otomatik seçili gelmeli
        $departmentFilters = [];
        if ($department_id) {
            $department = (new Department())->find($department_id);
            if (!(isAuthorized("submanager") or $this->currentUser->id == $department->chairperson_id)) {
                throw new Exception("Bu bölüme yeni kullanıcı ekleme yetkiniz yok");
            }
            //$departmentFilters["id"] = $department_id; //todo otomatik seçim olmayında sadece tek bir bölüm gösterilmesinin çok anlamı yok
        }
        if ($program_id) {
            $program = (new Program())->find($program_id);
            if (!(isAuthorized("submanager") or $this->currentUser->id == $program?->getDepartment()?->chairperson_id)) {
                throw new Exception("Bu programa yeni kullanıcı ekleme yetkiniz yok");
            }
        }
        if (!(isset($department) or isset($program))) {
            if (!isAuthorized("submanager")) {
                throw new Exception("Yeni kullanıcı ekleme yetkiniz yok");
            }
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Kullanıcı Ekle",
            "departments" => (new DepartmentController())->getDepartmentsList($departmentFilters),
        ]);

        $this->callView("admin/users/adduser", $this->view_data);
    }

    /**
     * @throws Exception
     */
    public function ProfileAction($id = null)
    {
        $userController = new UserController();
        if (is_null($id)) {
            $user = $userController->getCurrentUser();
        } else {
            $user = (new User())->find($id);
        }
        if (!isAuthorized("submanager", false, $user)) {
            throw new Exception("Bu profili görme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('profilepage');
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "user" => $user,
            "page_title" => $user->getFullName() . " Profil Sayfası",
            "lesson_list" => $user->getLessonsList(),
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => 'user',
                    'owner_id' => $user->id,
                    'type' => 'lesson',
                    'semester_no' => ['in' => getSemesterNumbers()]
                ],
                true
            ),
        ]);
        $this->callView("admin/profile", $this->view_data);
    }

    /**
     * @throws Exception
     */
    public function EditUserAction($id = null)
    {
        $userController = new UserController();
        if (is_null($id)) {
            $user = $userController->getCurrentUser();
        } else {
            $user = (new User())->find($id);
        }
        if (!isAuthorized("submanager", false, $user)) {
            throw new Exception("Kullanıcı düzenleme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "user" => $user,
            "page_title" => $user->getFullName() . " Kullanıcı Düzenle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "programController" => new ProgramController()]);
        $this->callView("admin/users/edituser", $this->view_data);
    }

    public function importUsersAction()
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Kullanıcı İçe aktarma yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => " Kullanıcı İçe aktar",
        ]);
        $this->callView("admin/users/importusers", $this->view_data);
    }


    /*
     * Lesson Routes
     */
    /**
     * @throws Exception
     */
    public function lessonAction($id = null)
    {
        if (!is_null($id)) {
            /**
             * @var Lesson $lesson
             */
            $lesson = (new Lesson())->find($id);
        } else {
            throw new Exception("Ders İd numarası belirtilmelidir");
        }
        if (!isAuthorized("submanager", false, $lesson)) {
            throw new Exception("Bu dersi görme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('singlepages');
        $this->view_data = array_merge($this->view_data, [
            "lesson" => $lesson,
            "page_title" => $lesson->name . " Sayfası",
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => 'lesson',
                    'owner_id' => $lesson->id,
                    'type' => 'lesson',
                    'semester_no' => ['in' => getSemesterNumbers()]
                ],
                true
            ),
            'combineLessonList' => (new Lesson())->get()->where(['lecturer_id' => $lesson->lecturer_id, '!id' => $lesson->id])->all(),
        ]);
        $this->callView("admin/lessons/lesson", $this->view_data);
    }

    public function ListLessonsAction()
    {
        if (!isAuthorized("department_head")) {
            throw new Exception("Ders listesini görme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('listpages');
        $lessonController = new LessonController();
        $this->view_data = array_merge($this->view_data, [
            "lessonController" => $lessonController,
            "page_title" => "Ders Listesi"
        ]);
        if ($this->currentUser->role == "department_head") {
            $this->view_data['lessons'] = $lessonController->getListByFilters(['department_id' => $this->currentUser->department_id]);
        } else $this->view_data['lessons'] = $lessonController->getListByFilters();
        $this->callView("admin/lessons/listlessons", $this->view_data);
    }

    public function AddLessonAction()
    {
        if (!isAuthorized("department_head")) {
            throw new Exception("Ders ekleme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
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
    }

    /**
     * @throws Exception
     */
    public function EditLessonAction($id = null): void
    {
        $lessonController = new LessonController();
        if (!is_null($id)) {
            $lesson = (new Lesson())->find($id);
        } else {
            throw new Exception("Belirtilen ders bulunamadı");
        }
        if (!isAuthorized("submanager", false, $lesson)) {
            throw new Exception("Bu dersi düzenleme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
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
            $this->view_data['lecturers'][] = (new User())->find($lesson->lecturer_id);
        } elseif ($this->currentUser->role == "lecturer") {
            $this->view_data['lecturers'][] = (new User())->find($lesson->lecturer_id);
        } else$this->view_data['lecturers'] = $userController->getListByFilters();
        $this->callView("admin/lessons/editlesson", $this->view_data);
    }

    public function importLessonsAction()
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Ders İçe aktarma yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => " Ders İçe aktar",
        ]);
        $this->callView("admin/lessons/importlessons", $this->view_data);
    }

    /*
     * Classroom Routes
     */
    /**
     * @throws Exception
     */
    public function classroomAction($id = null)
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Derslik sayfasını görme yetkiniz yok");
        }
        if (!is_null($id)) {
            $classroom = (new Classroom())->find($id);
        } else {
            throw new Exception("Derslik id Numarası belirtilmemiş");
        }
        $this->assetManager->loadPageAssets('singlepages');
        $this->view_data = array_merge($this->view_data, [
            "classroom" => $classroom,
            "page_title" => $classroom->name . " Sayfası",
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => 'classroom',
                    'owner_id' => $classroom->id,
                    'type' => 'lesson',
                    'semester_no' => ['in' => getSemesterNumbers()]
                ], true),
        ]);
        $this->callView("admin/classrooms/classroom", $this->view_data);
    }

    public function ListClassroomsAction()
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Derslik listesini görme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('listpages');
        $classroomController = new ClassroomController();
        $this->view_data = array_merge($this->view_data, [
            "classroomController" => $classroomController,
            "classrooms" => $classroomController->getClassroomsList(),
            "page_title" => "Derslik Listesi"
        ]);
        $this->callView("admin/classrooms/listclassrooms", $this->view_data);
    }

    public function AddClassroomAction()
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Yeni derslik ekleme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
        $classroomController = new ClassroomController();
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Derslik Ekle",
            "classroomTypes" => $classroomController->getTypeList()
        ]);
        $this->callView("admin/classrooms/addclassroom", $this->view_data);
    }

    /**
     * @throws Exception
     */
    public function editClassroomAction($id = null)
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Bu dersliği düzenleme yetkiniz yok");
        }
        $classroomController = new ClassroomController();
        if (!is_null($id)) {
            $classroom = (new Classroom())->find($id);
        } else {
            throw new Exception("Derslik Bulunamadı");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "classroomController" => $classroomController,
            "classroom" => $classroom,
            "classroomTypes" => $classroomController->getTypeList(),
            "page_title" => $classroom->name . "Düzenle",
        ]);
        $this->callView("admin/classrooms/editclassroom", $this->view_data);
    }

    /*
     * Department Routes
     */
    public function departmentAction($id = null)
    {
        $departmentController = new DepartmentController();
        if (!is_null($id)) {
            $department = (new Department())->find($id);
        } else {
            throw new Exception("İd belirtilmemiş");
        }
        if (!isAuthorized("submanager", false, $department)) {
            throw new Exception("Bu bölüm sayfasını görme yetkiniz yok");
        }
        $this->assetManager->addCss("https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-2.2.1/datatables.min.css");
        $this->assetManager->addJs("https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-2.2.1/datatables.min.js");
        $this->assetManager->addJs("/assets/js/data_table.js");
        $this->assetManager->loadPageAssets('singlepages');
        $this->view_data = array_merge($this->view_data, [
            "department" => $department,
            "page_title" => $department->name . " Sayfası"
        ]);
        $this->callView("admin/departments/department", $this->view_data);
    }

    public function ListDepartmentsAction()
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Bölümler listesini görmek için yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('listpages');
        $departmentController = new DepartmentController();
        $this->view_data = array_merge($this->view_data, [
            "departmentController" => $departmentController,
            "departments" => $departmentController->getDepartmentsList(),
            "page_title" => "Bölüm Listesi"
        ]);
        $this->callView("admin/departments/listdepartments", $this->view_data);
    }

    public function AddDepartmentAction()
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Yeni Bölüm ekleme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Bölüm Ekle",
            "lecturers" => $this->view_data["userController"]->getLecturerList(),
        ]);
        $this->callView("admin/departments/adddepartment", $this->view_data);
    }

    /**
     * @throws Exception
     */
    public function editDepartmentAction($id = null)
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Bu bölümü düzenleme yetkiniz yok");
        }
        $departmentController = new DepartmentController();
        if (!is_null($id)) {
            $department = (new Department())->find($id);
        } else {
            throw new Exception("Bölüm bulunamadı");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "departmentController" => $departmentController,
            "department" => $department,
            "page_title" => $department->name . " Düzenle",//todo ders olmayınca hata veriyor.
            "lecturers" => $this->view_data["userController"]->getLecturerList(),
        ]);
        $this->callView("admin/departments/editdepartment", $this->view_data);
    }

    /*
     * Program Routes
     */
    /**
     * @throws Exception
     */
    public function programAction($id = null)
    {
        $programController = new ProgramController();
        if (!is_null($id)) {
            $program = (new Program())->find($id);
            if (!$program) {
                throw new Exception("Belirtilen Program bulunamadı");
            }
        } else {
            throw new Exception("Program id değeri belirtilmelidir");
        }
        if (!isAuthorized("submanager", false, $program)) {
            throw new Exception("Bu programı görüntülemek için yetkiniz yok");
        }
        $this->assetManager->addCss("https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-2.2.1/datatables.min.css");
        $this->assetManager->addJs("https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-2.2.1/datatables.min.js");
        $this->assetManager->addJs("/assets/js/data_table.js");
        $this->assetManager->loadPageAssets('singlepages');

        $this->view_data = array_merge($this->view_data, [
            "program" => $program,
            "page_title" => $program->name . " Sayfası",
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(['owner_type' => 'program', 'owner_id' => $program->id, 'type' => 'lesson'], true),
        ]);
        $this->callView("admin/programs/program", $this->view_data);
    }

    /**
     * @param $department_id
     * @return void
     */
    public function ListProgramsAction($department_id = null): void
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Programlar listesini görmek için yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('listpages');
        $programController = new ProgramController();
        $this->view_data = array_merge($this->view_data, [
            "programController" => $programController,
            "programs" => $programController->getProgramsList($department_id),
            "page_title" => "Program Listesi",
        ]);
        $this->callView("admin/programs/listprograms", $this->view_data);
    }

    public function AddProgramAction($department_id = null)
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Program ekleme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Program Ekle",
            "departments" => (new DepartmentController())->getDepartmentsList(),
            "department_id" => $department_id
        ]);
        $this->callView("admin/programs/addprogram", $this->view_data);
    }

    public function editProgramAction($id = null)
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Program düzenleme yetkiniz yok");
        }
        $programController = new ProgramController();
        if (!is_null($id)) {
            $program = (new Program())->find($id);
            if (!$program) {
                throw new Exception("Belirtilen Program bulunamadı");
            }
        } else {
            throw new Exception("Program id numarası belirtilmelidir");
        }
        $this->assetManager->loadPageAssets('formpages');
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

    /**
     * @throws Exception
     */
    public function EditScheduleAction($department_id = null)
    {
        if (!isAuthorized("department_head")) {
            throw new Exception("Ders programı düzenleme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('editschedule');
        $userController = new UserController();
        $currentUser = $userController->getCurrentUser();
        $departmentController = new DepartmentController();
        if ($userController->canUserDoAction(8)) {
            $departments = $departmentController->getDepartmentsList();
        } elseif ($userController->canUserDoAction(7) and $currentUser->role == "department_head") {
            $departments = [(new Department())->find($currentUser->department_id ?? 0) ?: throw new Exception("Bölüm başkanının bölüm bilgisi yok")];
        } else {
            throw new Exception("Bu işlem için yetkiniz yok");
        }
        $this->view_data = array_merge($this->view_data, [
            "scheduleController" => new ScheduleController(),
            "departments" => $departments,
            "page_title" => "Takvim Düzenle",
            "current_semesters" => getCurrentSemester()
        ]);
        $this->callView("admin/schedules/editschedule", $this->view_data);
    }

    /**
     * @throws Exception
     */
    public function exportScheduleAction()
    {
        if (!isAuthorized("department_head")) {
            throw new Exception("Ders programı Dışa aktarma yetkiniz yok");
        }
        $userController = new UserController();
        $this->assetManager->loadPageAssets('exportschedule');
        $departmentController = new DepartmentController();
        if ($userController->canUserDoAction(8)) {
            $departments = $departmentController->getDepartmentsList();
        } elseif ($userController->canUserDoAction(7) and $this->currentUser->role == "department_head") {
            $departments = [(new Department())->find($this->currentUser->department_id)];
        } else {
            throw new Exception("Bu işlem için yetkiniz yok");
        }
        $this->view_data = array_merge($this->view_data, [
            "scheduleController" => new ScheduleController(),
            "departments" => $departments,
            "page_title" => "Ders Programı Dışa aktar",
            "classrooms" => (new ClassroomController())->getClassroomsList()
        ]);
        if ($this->currentUser->role == "department_head") {
            $this->view_data['lecturers'] = $userController->getListByFilters(['department_id' => $this->currentUser->department_id]);
        } else $this->view_data['lecturers'] = $userController->getListByFilters();
        $this->callView("admin/schedules/exportschedule", $this->view_data);
    }

    /*
     * Ayarlar
     */
    public function SettingsAction()
    {
        if (!isAuthorized("submanager")) {
            throw new Exception("Ayarlar sayfasına erişim yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Ayarlar",
        ]);
        $this->callView("admin/settings/settings", $this->view_data);
    }

    /**
     * @throws Exception
     */
    public function downloadAction($filename)
    {
        $filename = urldecode($filename);
        $filename = basename($filename);
        $filePath = $_ENV["DOWNLOAD_PATH"] . "/" . $filename;
        // Dosya yolu geçerli mi?
        if (!file_exists($filePath)) {
            if ($_ENV["DEBUG"]) {
                error_log(__LINE__ . ". satırda filePath değişkeni:" . var_export($filePath, true));
            }

            throw new Exception("İndirilecek dosya bulunamadı", 404);
        }

        // Güvenlik önlemi: Gerçek yol kontrolü (isteğe bağlı)
        $realPath = realpath($filePath);
        $baseDir = realpath($_ENV["DOWNLOAD_PATH"]); // indirilebilir dosyaların olduğu klasör
        if (!str_starts_with($realPath, $baseDir)) {
            throw new Exception("Bu dosyaya erişim izniniz yok.", 403);
        }

        // Dosya bilgileri
        $filename = basename($filePath);
        $encodedFilename = rawurlencode($filename);
        $fileSize = filesize($filePath);
        $fileType = mime_content_type($filePath);

        // İndirme başlıkları
        header("Content-Description: File Transfer");
        header("Content-Type: " . $fileType);
        header("Content-Disposition: attachment; filename=\"$filename\"; filename*=UTF-8''$encodedFilename");
        header("Content-Length: " . $fileSize);
        header("Cache-Control: must-revalidate");
        header("Pragma: public");
        header("Expires: 0");

        // Dosyayı gönder
        readfile($filePath);
        exit;
    }
}