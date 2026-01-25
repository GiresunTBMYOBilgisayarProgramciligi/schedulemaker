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
use App\Models\Log;
use App\Models\Program;
use App\Core\Gate;
use App\Models\User;
use Exception;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSettingValue;

/**
 * AdminRouter Sınıfı
 * /admin altında gelen istekleri yönetir.
 */
class AdminRouter extends Router
{

    private User|false $currentUser = false;

    public function __construct()
    {
        parent::__construct();
        $this->beforeAction();
    }

    /**
     * Aksiyonlardan önce çalıştırılan kontrol mekanizması
     * @throws Exception
     */
    private function beforeAction(): void
    {
        $userController = new UserController();
        $this->currentUser = $userController->getCurrentUser();
        $this->view_data['currentUser'] = $this->currentUser;
        if (!$this->currentUser) {
            // Giriş yapılmamışsa gidilmek istenen URL'yi kaydet (AJAX değilse)
            if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
                $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            }
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
        $this->view_data = array_merge($this->view_data, [
            "departmentController" => new DepartmentController(),
            "classroomController" => new ClassroomController(),
            "lessonController" => new LessonController(),
            "programController" => new ProgramController(),
            'userController' => new UserController(),
            "programs" => (new Program())->get()->where(['active' => true])->with(['lecturers', 'lessons', 'department' => ['with' => ['chairperson']]])->all(),
            "page_title" => "Anasayfa"
        ]);
            //müdür altındaki kullanıcılar için eğer program tanımlı ise programın ders programı yoksa kullanıcının ders programı
            if (!is_null($this->currentUser->program_id)) {
                $this->assetManager->addCss("/assets/css/schedule.css");
                $this->view_data["scheduleHTML"] = (new ScheduleController())->getSchedulesHTML(['owner_type' => 'program', 'owner_id' => $this->currentUser->program_id, 'type' => 'lesson'], true);
            } else {
                $this->assetManager->addCss("/assets/css/schedule.css");
                $this->view_data["scheduleHTML"] = (new ScheduleController())->getSchedulesHTML(['owner_type' => 'user', 'owner_id' => $this->currentUser->id, 'type' => 'lesson'], true);
            }
        $this->callView("admin/index/index");
    }

    /*
     * User Routes
     */
    public function ListUsersAction()
    {
        Gate::authorize("view", User::class, false, "Kullanıcı listesini görme yetkiniz yok");
        $this->assetManager->loadPageAssets('listpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Kullanıcı Listesi",
        ]);
        if ($this->currentUser->role == "department_head") {
            $this->view_data['users'] = (new User())->get()->where(['department_id' => $this->currentUser->department_id])->with(['department', 'program'])->all();
        } else
            $this->view_data['users'] = (new User())->get()->with(['department', 'program'])->all();
        $this->callView("admin/users/listusers");
    }

    /**
     * @throws Exception
     */
    public function AddUserAction(?int $department_id = null, ?int $program_id = null)
    {
        if ($department_id) {
            $department = (new Department())->find($department_id) ?: throw new Exception("Bölüm Bulunamadı");
            if (!(Gate::authorize("update", $department))) {
                throw new Exception("Bu bölüme yeni kullanıcı ekleme yetkiniz yok");
            }
        }
        if ($program_id) {
            $program = (new Program())->find($program_id) ?: throw new Exception("Program bulunamadı");
            if (!(Gate::authorize("update", $program))) {
                throw new Exception("Bu programa yeni kullanıcı ekleme yetkiniz yok");
            }
        }
        if (!(isset($department) or isset($program))) {
            Gate::authorizeRole("submanager", false, "Yeni kullanıcı ekleme yetkiniz yok");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Kullanıcı Ekle",
            "userController" => new UserController(),
            "departments" => (new Department())->get()->where(['active' => true])->all(),
            "department_id" => $department_id,
            "program_id" => $program_id
        ]);

        $this->callView("admin/users/adduser");
    }

    /**
     * @throws Exception
     */
    public function ProfileAction($id = null)
    {
        if (is_null($id)) {
            $user = $this->currentUser;
        } else {
            $user = (new User())->get()->where(['id' => $id])->with(['department', 'program', 'lessons' => ['with' => ['department', 'program']], 'schedules' => ['with' => ['items']]])->first();
            if (!$user)
                throw new Exception("Kullanıcı bulunamadı");
        }

        Gate::authorize("view", $user, "Bu profili görme yetkiniz yok");
        $this->assetManager->loadPageAssets('profilepage');
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "user" => $user,
            "page_title" => $user->getFullName() . " Profil Sayfası",
            "userController" => new UserController(),
            "departments" => (new Department())->get()->where(['active' => true])->all(),
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => 'user',
                    'owner_id' => $user->id,
                    'type' => 'lesson',
                    'semester_no' => getSemesterNumbers()
                ],
                preference_mode: true,
                no_card: true
            ),
            "midtermScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => 'user',
                    'owner_id' => $user->id,
                    'type' => 'midterm-exam',
                    'semester_no' => getSemesterNumbers()
                ],
                preference_mode: true,
                no_card: true
            ),
            "finalScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => 'user',
                    'owner_id' => $user->id,
                    'type' => 'final-exam',
                    'semester_no' => getSemesterNumbers()
                ],
                preference_mode: true,
                no_card: true
            ),
            "makeupScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => 'user',
                    'owner_id' => $user->id,
                    'type' => 'makeup-exam',
                    'semester_no' => getSemesterNumbers()
                ],
                preference_mode: true,
                no_card: true
            ),
        ]);
        $this->callView("admin/users/profile");
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
            $user = (new User())->find($id) ?: throw new Exception("Kullanıcı bulunamadı");
        }
        Gate::authorize("update", $user, "Kullanıcı düzenleme yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "user" => $user,
            "page_title" => $user->getFullName() . " Kullanıcı Düzenle",
            "departments" => (new Department())->get()->where(['active' => true])->all(),
            "programController" => new ProgramController(),
            "userController" => new UserController(),
        ]);
        $this->callView("admin/users/edituser");
    }

    public function importUsersAction()
    {
        Gate::authorizeRole("submanager", false, "Kullanıcı İçe aktarma yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => " Kullanıcı İçe aktar",
        ]);
        $this->callView("admin/users/importusers");
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
            $lesson = (new Lesson())->where(['id' => $id])->with(['program', 'lecturer' => ['with' => ['lessons']], 'department', 'parentLesson' => ['with' => ['program']], 'childLessons' => ['with' => ['program']]])->first() ?: throw new Exception("Ders bulunamadı");
        } else {
            throw new Exception("Ders İd numarası belirtilmelidir");
        }
        Gate::authorize("view", $lesson, "Bu dersi görme yetkiniz yok");
        $this->assetManager->loadPageAssets('singlepages');
        $this->view_data = array_merge($this->view_data, [
            "lesson" => $lesson,
            "page_title" => $lesson->name . " Sayfası",
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => 'lesson',
                    'owner_id' => $lesson->id,
                    'type' => 'lesson',
                    'semester_no' => getSemesterNumbers()
                ],
                preference_mode: true
            ),
            'combineLessonList' => (new Lesson())->get()->where(['lecturer_id' => $lesson->lecturer_id, '!id' => $lesson->id, 'semester' => getSettingValue('semester'), 'academic_year' => getSettingValue('academic_year')])->with(['program', 'lecturer' => ['with' => ['lessons']], 'department', 'parentLesson' => ['with' => ['program']], 'childLessons' => ['with' => ['program']]])->all(),
        ]);
        $this->callView("admin/lessons/lesson");
    }

    public function ListLessonsAction()
    {
        Gate::authorize("view", Lesson::class, false, "Ders listesini görme yetkiniz yok");
        $this->assetManager->loadPageAssets('listpages');
        $lessonController = new LessonController();
        $this->view_data = array_merge($this->view_data, [
            "lessonController" => $lessonController,
            "page_title" => "Ders Listesi"
        ]);
        if ($this->currentUser->role == "department_head") {
            $this->view_data['lessons'] = (new Lesson())->get()->where(['department_id' => $this->currentUser->department_id])->with(['program', 'lecturer', 'department', 'parentLesson' => ['with' => ['program']]])->all();
        } else
            $this->view_data['lessons'] = (new Lesson())->get()->with(['program', 'lecturer', 'department', 'parentLesson' => ['with' => ['program']]])->all();
        $this->callView("admin/lessons/listlessons");
    }

    public function AddLessonAction(?int $program_id = null)
    {
        Gate::authorize("create", Lesson::class, false, "Ders ekleme yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Ders Ekle",
            "departments" => (new Department())->get()->where(['active' => true])->all(),
            "lessonController" => new LessonController(),
            "classroomTypes" => (new ClassroomController())->getTypeList(),
            "program_id" => $program_id
        ]);
        if ($this->currentUser->role == "department_head") {
            $this->view_data['lecturers'] = (new User())->get()->where(['department_id' => $this->currentUser->department_id, '!role' => ['admin', 'user']])->all();
        } else
            $this->view_data['lecturers'] = (new User())->get()->where(['!role' => ["in" => ['admin', 'user']]])->all();
        if ($program_id) {
            $program = (new Program())->find($program_id);
            if ($program) {
                $this->view_data['department_id'] = $program->department_id;
            }
        }
        $this->callView("admin/lessons/addlesson");
    }

    /**
     * @throws Exception
     */
    public function EditLessonAction($id = null): void
    {
        $lessonController = new LessonController();
        if (!is_null($id)) {
            $lesson = (new Lesson())->find($id) ?: throw new Exception("Ders bulunamadı");
        } else {
            throw new Exception("Ders id numarası belirtilmelidir");
        }
        Gate::authorize("update", $lesson, "Bu dersi düzenleme yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "lessonController" => new LessonController(),
            "lesson" => $lesson,
            "page_title" => $lesson->getFullName() . " Düzenle",
            "departments" => (new Department())->get()->where(['active' => true])->all(),
            "programController" => new ProgramController(),
            "classroomTypes" => (new ClassroomController())->getTypeList()
        ]);
        $userController = new UserController();
        if ($this->currentUser->role == "department_head") {
            $this->view_data['lecturers'] = (new User())->get()->where(['department_id' => $this->currentUser->department_id, 'role' => 'lecturer'])->all();
            /* Bölümsüz hocalar için dersin hocası da listeye ekleniyor. (Okul dışından gelen hocalar için)*/
            $this->view_data['lecturers'][] = (new User())->find($lesson->lecturer_id);
        } elseif ($this->currentUser->role == "lecturer") {
            $this->view_data['lecturers'][] = (new User())->find($lesson->lecturer_id);
        } else
            $this->view_data['lecturers'] = (new User())->get()->where(['!role' => ["in" => ['admin', 'user']]])->all();
        $this->callView("admin/lessons/editlesson");
    }

    public function importLessonsAction()
    {
        Gate::authorizeRole("submanager", false, "Ders İçe aktarma yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => " Ders İçe aktar",
        ]);
        $this->callView("admin/lessons/importlessons");
    }

    /*
     * Classroom Routes
     */
    /**
     * @throws Exception
     */
    public function classroomAction($id = null)
    {
        Gate::authorizeRole("submanager", false, "Derslik sayfasını görme yetkiniz yok");
        if (!is_null($id)) {
            $classroom = (new Classroom())->where(['id' => $id])->with(['schedules' => ['with' => ['items']]])->first() ?: throw new Exception("Derslik bulunamadı");
        } else {
            throw new Exception("Derslik id Numarası belirtilmemiş");
        }
        $this->assetManager->loadPageAssets('classroompage');
        $this->view_data = array_merge($this->view_data, [
            "classroom" => $classroom,
            "page_title" => $classroom->name . " Sayfası",
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => 'classroom',
                    'owner_id' => $classroom->id,
                    'type' => 'lesson',
                    'semester_no' => getSemesterNumbers()
                ],
                preference_mode: true
            ),
        ]);
        $this->callView("admin/classrooms/classroom");
    }

    public function ListClassroomsAction()
    {
        Gate::authorizeRole("submanager", false, "Derslik listesini görme yetkiniz yok");
        $this->assetManager->loadPageAssets('listpages');
        $classroomController = new ClassroomController();
        $this->view_data = array_merge($this->view_data, [
            "classroomController" => $classroomController,
            "classrooms" => $classroomController->getClassroomsList(),
            "page_title" => "Derslik Listesi"
        ]);
        $this->callView("admin/classrooms/listclassrooms");
    }

    public function AddClassroomAction()
    {
        Gate::authorize("create", Classroom::class, "Yeni derslik ekleme yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $classroomController = new ClassroomController();
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Derslik Ekle",
            "classroomTypes" => $classroomController->getTypeList()
        ]);
        $this->callView("admin/classrooms/addclassroom");
    }

    /**
     * @throws Exception
     */
    public function editClassroomAction($id = null)
    {
        $classroomController = new ClassroomController();
        if (!is_null($id)) {
            $classroom = (new Classroom())->find($id) ?: throw new Exception("Derslik bulunamadı");
            Gate::authorize("update", $classroom, "Bu dersliği düzenleme yetkiniz yok");
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
        $this->callView("admin/classrooms/editclassroom");
    }

    /*
     * Department Routes
     */
    public function departmentAction($id = null)
    {
        $departmentController = new DepartmentController();
        if (!is_null($id)) {
            $department = (new Department())->get()->where(["id" => $id])->with(["programs" => ['with' => ['department']], "chairperson", "lessons" => ['with' => ['lecturer', 'program']], "users" => ['with' => ['program']]])->first() ?: throw new Exception("Bölüm bulunamadı");
        } else {
            throw new Exception("İd belirtilmemiş");
        }
        Gate::authorize("view", $department, "Bu bölüm sayfasını görme yetkiniz yok");
        $this->assetManager->loadPageAssets('listpages');
        $this->assetManager->loadPageAssets('singlepages');
        $this->view_data = array_merge($this->view_data, [
            "department" => $department,
            "page_title" => $department->name . " Sayfası"
        ]);
        $this->callView("admin/departments/department");
    }

    public function ListDepartmentsAction()
    {
        Gate::authorize("view", Department::class, false, "Bölümler listesini görmek için yetkiniz yok");
        $this->assetManager->loadPageAssets('listpages');
        $this->view_data = array_merge($this->view_data, [
            "departments" => (new Department())->get()->with(["chairperson"])->all(),
            "page_title" => "Bölüm Listesi"
        ]);
        $this->callView("admin/departments/listdepartments");
    }

    public function AddDepartmentAction()
    {
        Gate::authorize("create", Department::class, "Yeni Bölüm ekleme yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Bölüm Ekle",
            "lecturers" => (new User())->get()->where(["!role" => ['in' => ["user", "admin"]]])->all()
        ]);
        $this->callView("admin/departments/adddepartment");
    }

    /**
     * @throws Exception
     */
    public function editDepartmentAction($id = null)
    {
        $departmentController = new DepartmentController();
        if (!is_null($id)) {
            $department = (new Department())->find($id) ?: throw new Exception("Bölüm bulunamadı");
            Gate::authorize("update", $department, "Bu bölümü düzenleme yetkiniz yok");
        } else {
            throw new Exception("Bölüm bulunamadı");
        }
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "departmentController" => $departmentController,
            "department" => $department,
            "page_title" => $department->name ?? "" . " Düzenle",
            "lecturers" => (new User())->get()->where(["!role" => ['in' => ["user", "admin"]]])->all(),
        ]);
        $this->callView("admin/departments/editdepartment");
    }

    /*
     * Program Routes
     */
    /**
     * @throws Exception
     */
    public function programAction($id = null)
    {
        if (!is_null($id)) {
            $program = (new Program())->get()->where(["id" => $id])->with(['department' => ['with' => ['chairperson']], 'lecturers', 'lessons' => ['with' => ['lecturer']], 'schedules' => ['with' => ['items']]])->first() ?: throw new Exception("Program bulunamadı");
        } else {
            throw new Exception("Program id değeri belirtilmelidir");
        }
        Gate::authorize("view", $program, "Bu programı görüntülemek için yetkiniz yok");
        $this->assetManager->loadPageAssets('listpages');
        $this->assetManager->loadPageAssets('singlepages');

        $this->view_data = array_merge($this->view_data, [
            "program" => $program,
            "page_title" => $program->name . " Sayfası",
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(['owner_type' => 'program', 'owner_id' => $program->id, 'type' => 'lesson'], preference_mode: true),
        ]);
        $this->callView("admin/programs/program");
    }

    /**
     * @return void
     * @throws Exception
     */
    public function ListProgramsAction(): void
    {
        Gate::authorize("view", Program::class, false, "Programlar listesini görmek için yetkiniz yok");
        $this->assetManager->loadPageAssets('listpages');
        $this->view_data = array_merge($this->view_data, [
            "programs" => (new Program())->get()->with(['department'])->all(),
            "page_title" => "Program Listesi",
        ]);
        $this->callView("admin/programs/listprograms");
    }

    public function AddProgramAction($department_id = null)
    {
        Gate::authorize("create", Program::class, false, "Program ekleme yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Program Ekle",
            "departments" => (new Department())->get()->where(['active' => true])->all(),
            "department_id" => $department_id
        ]);
        $this->callView("admin/programs/addprogram");
    }

    public function editProgramAction($id = null)
    {
        $programController = new ProgramController();
        if (!is_null($id)) {
            $program = (new Program())->find($id) ?: throw new Exception("Program bulunamadı");
        } else {
            throw new Exception("Program id numarası belirtilmelidir");
        }
        Gate::authorize("update", $program, "Program düzenleme yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "programController" => $programController,
            "program" => $program,
            "departments" => (new Department())->get()->where(['active' => true])->all(),
            "page_title" => $program->name ?? "" . " Düzenle",
        ]);
        $this->callView("admin/programs/editprogram");
    }

    /*
     * Takvim işlemleri
     */

    /**
     * @throws Exception
     */
    public function EditScheduleAction($department_id = null)
    {
        Gate::authorizeRole("department_head", false, "Ders programı düzenleme yetkiniz yok");
        $this->assetManager->loadPageAssets('editschedule');
        if (Gate::allowsRole("submanager")) {
            $departments = (new Department())->get()->where(['active' => true])->all();
        } elseif (Gate::allowsRole("department_head") and $this->currentUser->role == "department_head") {
            $departments = (new Department())->get()->where(['active' => true, 'id' => $this->currentUser->department_id])->all() ?: throw new Exception("Bölüm başkanının bölüm bilgisi yok");
        } else {
            throw new Exception("Bu işlem için yetkiniz yok");
        }
        $this->view_data = array_merge($this->view_data, [
            "scheduleController" => new ScheduleController(),
            "departments" => $departments,
            "page_title" => "Ders Programı Düzenle",
            "classrooms" => (new ClassroomController())->getClassroomsList()
        ]);
        if ($this->currentUser->role == "department_head") {
            $this->view_data['lecturers'] = (new User())->get()->where(['department_id' => $this->currentUser->department_id, '!role' => ["in" => ['admin', 'user']]])->all();
        } else
            $this->view_data['lecturers'] = (new User())->get()->where(['!role' => ["in" => ['admin', 'user']]])->all();
        $this->callView("admin/schedules/editschedule");
    }

    public function EditExamScheduleAction($department_id = null)
    {
        Gate::authorizeRole("department_head", false, "Sınav programı düzenleme yetkiniz yok");
        $this->assetManager->loadPageAssets('editexamschedule');
        $userController = new UserController();
        if (Gate::allowsRole("submanager")) {
            $departments = (new Department())->get()->where(['active' => true])->all();
        } elseif (Gate::allowsRole("department_head") and $this->currentUser->role == "department_head") {
            $departments = (new Department())->get()->where(['active' => true, 'id' => $this->currentUser->department_id])->all() ?: throw new Exception("Bölüm başkanının bölüm bilgisi yok");
        } else {
            throw new Exception("Bu işlem için yetkiniz yok");
        }
        $this->view_data = array_merge($this->view_data, [
            "scheduleController" => new ScheduleController(),
            "departments" => $departments,
            "page_title" => "Sınav Programını Düzenle",
            "classrooms" => (new ClassroomController())->getClassroomsList()
        ]);
        if ($this->currentUser->role == "department_head") {
            $this->view_data['lecturers'] = (new User())->get()->where(['department_id' => $this->currentUser->department_id, '!role' => ['admin', 'user']])->all();
        } else
            $this->view_data['lecturers'] = (new User())->get()->where(['!role' => ["in" => ['admin', 'user']]])->all();
        $this->callView("admin/schedules/editexamschedule");
    }

    /**
     * @throws Exception
     */
    public function exportScheduleAction()
    {
        Gate::authorizeRole("department_head", false, "Ders programı Dışa aktarma yetkiniz yok");
        $userController = new UserController();
        $this->assetManager->loadPageAssets('exportschedule');
        if (Gate::allowsRole("submanager")) {
            $departments = (new Department())->get()->where(['active' => true])->all();
        } elseif (Gate::allowsRole("department_head") and $this->currentUser->role == "department_head") {
            $departments = [(new Department())->find($this->currentUser->department_id)] ?: throw new Exception("Bölüm bulunamadı");
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
        } else
            $this->view_data['lecturers'] = $userController->getListByFilters();
        $this->callView("admin/schedules/exportschedule");
    }

    /*
     * Ayarlar
     */
    public function SettingsAction()
    {
        Gate::authorizeRole("submanager", false, "Ayarlar sayfasına erişim yetkiniz yok");
        $this->assetManager->loadPageAssets('formpages');
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Ayarlar",
            "settings" => (new SettingsController())->getSettings()
        ]);
        $this->callView("admin/settings/settings");
    }

    public function LogsAction()
    {
        Gate::authorizeRole("submanager", false, "Kayıtlara erişim yetkiniz yok");
        $this->assetManager->loadPageAssets('listpages');
        $logs = (new Log())->get()->orderBy('created_at', 'DESC')->limit(500)->all();
        $this->view_data = array_merge($this->view_data, [
            "page_title" => "Kayıtlar",
            "logs" => $logs,
        ]);
        $this->callView("admin/settings/logs");
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