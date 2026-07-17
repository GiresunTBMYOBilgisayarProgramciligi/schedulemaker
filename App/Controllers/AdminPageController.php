<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\AssetManager;
use App\Core\Gate;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Log;
use App\Models\Program;
use App\Models\User;
use App\Models\Unit;
use App\Models\Building;
use App\Repositories\ClassroomRepository;
use App\Repositories\DepartmentRepository;
use App\Repositories\UserRepository;
use App\Repositories\LessonRepository;
use App\Repositories\ProgramRepository;
use App\Repositories\UnitRepository;
use App\Repositories\BuildingRepository;
use App\Enums\ClassroomType;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Enums\UnitType;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSettingValue;
use Exception;

class AdminPageController extends Controller
{
    /**
     * @throws Exception
     */
    public function getIndexPageData(User $currentUser, AssetManager $assetManager): array
    {
        $view_data = [
            "departmentController" => new DepartmentController(),
            "classroomController" => new ClassroomController(),
            "lessonController" => new LessonController(),
            "programController" => new ProgramController(),
            'userController' => new UserController(),
            "programs" => (new ProgramRepository())->getActiveProgramsWithDetails(),
            "page_title" => "Anasayfa"
        ];
        
        if (!is_null($currentUser->program_id)) {
            $assetManager->addCss("/assets/css/schedule.css");
            $view_data["scheduleHTML"] = (new ScheduleController())->getSchedulesHTML(['owner_type' => OwnerType::PROGRAM->value, 'owner_id' => $currentUser->program_id, 'type' => 'lesson'], true);
        } else {
            $assetManager->addCss("/assets/css/schedule.css");
            $view_data["scheduleHTML"] = (new ScheduleController())->getSchedulesHTML(['owner_type' => OwnerType::USER->value, 'owner_id' => $currentUser->id, 'type' => 'lesson'], true);
        }
        
        return $view_data;
    }

    public function getListUsersPageData(User $currentUser, AssetManager $assetManager): array
    {
        Gate::authorize("view", User::class, "Kullanıcı listesini görme yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        $view_data = [
            "page_title" => "Kullanıcı Listesi",
        ];
        if ($currentUser->role == "department_head") {
            $view_data['users'] = (new UserRepository())->getUsersForDepartmentHead($currentUser->department_id);
        } else {
            $view_data['users'] = (new UserRepository())->getAllUsersWithDetails();
        }
        return $view_data;
    }

    /**
     * @throws Exception
     */
    public function getAddUserPageData(AssetManager $assetManager, ?int $department_id = null, ?int $program_id = null): array
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
        $assetManager->loadPageAssets('formpages');
        return [
            "page_title" => "Kullanıcı Ekle",
            "userController" => new UserController(),
            "units" => (new UnitRepository())->getAllUnits(),
            "departments" => (new DepartmentRepository())->getActiveDepartments(),
            "department_id" => $department_id,
            "program_id" => $program_id,
            "programs" => (new ProgramRepository())->getActiveProgramsWithDetails()
        ];
    }

    /**
     * @throws Exception
     */
    public function getProfilePageData(User $currentUser, AssetManager $assetManager, $id = null): array
    {
        if (is_null($id)) {
            $user = $currentUser;
        } else {
            $user = (new UserRepository())->findUserWithProfileDetails($id);
            if (!$user)
                throw new Exception("Kullanıcı bulunamadı");
        }

        Gate::authorize("view", $user, "Bu profili görme yetkiniz yok");
        $assetManager->loadPageAssets('profilepage');
        $assetManager->loadPageAssets('formpages');
        

        return [
            "user" => $user,
            "canEditSpecialFields" => Gate::allowsRole('submanager') || ($currentUser->role === 'department_head' && $currentUser->id !== $user->id),
            "page_title" => $user->getFullName() . " Profil Sayfası",
            "userController" => new UserController(),
            "units" => (new UnitRepository())->getAllUnits(),
            "departments" => (new DepartmentRepository())->getActiveDepartments(),
            "department_programs" => (new DepartmentRepository())->getDepartmentProgramsList($user->department_id ?? null),
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::USER->value,
                    'owner_id' => $user->id,
                    'type' => 'lesson',
                    'semester_no' => getSemesterNumbers()
                ],
                false,
                true
            ),
            "midtermScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::USER->value,
                    'owner_id' => $user->id,
                    'type' => ExamType::MIDTERM->value,
                    'semester_no' => getSemesterNumbers()
                ],
                false,
                true
            ),
            "finalScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::USER->value,
                    'owner_id' => $user->id,
                    'type' => ExamType::FINAL->value,
                    'semester_no' => getSemesterNumbers()
                ],
                false,
                true
            ),
            "makeupScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::USER->value,
                    'owner_id' => $user->id,
                    'type' => ExamType::MAKEUP->value,
                    'semester_no' => getSemesterNumbers()
                ],
                false,
                true
            ),
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditUserPageData(User $currentUser, AssetManager $assetManager, $id = null): array
    {
        if (is_null($id)) {
            $user = $currentUser;
        } else {
            $user = (new User())->find($id) ?: throw new Exception("Kullanıcı bulunamadı");
        }
        Gate::authorize("update", $user, "Kullanıcı düzenleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "user" => $user,
            "page_title" => $user->getFullName() . " Kullanıcı Düzenle",
            "units" => (new UnitRepository())->getAllUnits(),
            "departments" => (new DepartmentRepository())->getActiveDepartments(),
            "department_programs" => (new DepartmentRepository())->getDepartmentProgramsList($user->department_id ?? null),
            "programs" => (new ProgramRepository())->getActiveProgramsWithDetails(),
            "programController" => new ProgramController(),
            "userController" => new UserController(),
        ];
    }

    public function getImportUsersPageData(AssetManager $assetManager): array
    {
        Gate::authorizeRole("submanager", false, "Kullanıcı İçe aktarma yetkiniz yok");
        $assetManager->loadPageAssets('importpages');
        return [
            "page_title" => " Kullanıcı İçe aktar",
        ];
    }

    /**
     * @throws Exception
     */
    public function getLessonPageData(AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $lesson = (new LessonRepository())->findLessonWithDetails($id) ?: throw new Exception("Ders bulunamadı");
        } else {
            throw new Exception("Ders İd numarası belirtilmelidir");
        }
        Gate::authorize("view", $lesson, "Bu dersi görme yetkiniz yok");
        $assetManager->loadPageAssets('singlepages');
        $assetManager->loadPageAssets('formpages');
        $assetManager->addJs('/assets/js/admin/combineLesson.js');
        $assetManager->addJs('/assets/js/admin/combineExamLesson.js');
        
        return [
            "lesson" => $lesson,
            "page_title" => $lesson->name . " Sayfası",
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::LESSON->value,
                    'owner_id' => $lesson->id,
                    'type' => 'lesson',
                    'semester_no' => getSemesterNumbers()
                ],
                true,
                true
            ),
            "midtermScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::LESSON->value,
                    'owner_id' => $lesson->id,
                    'type' => ExamType::MIDTERM->value,
                    'semester_no' => getSemesterNumbers()
                ],
                true,
                true
            ),
            "finalScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::LESSON->value,
                    'owner_id' => $lesson->id,
                    'type' => ExamType::FINAL->value,
                    'semester_no' => getSemesterNumbers()
                ],
                true,
                true
            ),
            "makeupScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::LESSON->value,
                    'owner_id' => $lesson->id,
                    'type' => ExamType::MAKEUP->value,
                    'semester_no' => getSemesterNumbers()
                ],
                true,
                true
            ),
            'combineLessonList' => (new LessonRepository())->getCombineLessonList($lesson->lecturer_id, $lesson->id, getSettingValue('semester'), getSettingValue('academic_year')),
            'examCombineLessonList' => (new LessonRepository())->getExamCombineLessonList($lesson->id, getSettingValue('semester'), getSettingValue('academic_year')),
        ];
    }

    public function getListLessonsPageData(User $currentUser, AssetManager $assetManager): array
    {
        Gate::authorize("view", Lesson::class, "Ders listesini görme yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        $view_data = [
            "lessonController" => new LessonController(),
            "page_title" => "Ders Listesi"
        ];
        if ($currentUser->role == "department_head") {
            $view_data['lessons'] = (new LessonRepository())->getLessonsForDepartmentHead($currentUser->department_id);
        } else {
            $view_data['lessons'] = (new LessonRepository())->getAllLessonsWithDetails();
        }
        return $view_data;
    }

    public function getAddLessonPageData(User $currentUser, AssetManager $assetManager, ?int $program_id = null): array
    {
        Gate::authorize("create", Lesson::class, "Yeni ders ekleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        $view_data = [
            "page_title" => "Ders Ekle",
            "departments" => (new DepartmentRepository())->getActiveDepartments(),
            "units" => (new UnitRepository())->getAllUnits(),
            "lessonController" => new LessonController(),
            "classroomTypes" => ClassroomType::toArray(),
            "buildings" => (new BuildingRepository())->getAllBuildings(),
            "program_id" => $program_id
        ];
        if ($currentUser->role == "department_head") {
            $view_data['lecturers'] = (new User())->get()->where(['department_id' => $currentUser->department_id, '!role' => ['admin', 'user']])->all();
        } else {
            $view_data['lecturers'] = (new User())->get()->where(['!role' => ["in" => ['admin', 'user']]])->all();
        }
        if ($program_id) {
            $program = (new Program())->find($program_id);
            if ($program) {
                $view_data['department_id'] = $program->department_id;
            }
        }
        return $view_data;
    }

    /**
     * @throws Exception
     */
    public function getEditLessonPageData(User $currentUser, AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $lesson = (new LessonRepository())->findLessonWithDetails($id) ?: throw new Exception("Ders bulunamadı");
        } else {
            throw new Exception("Ders id numarası belirtilmelidir");
        }
        Gate::authorize("update", $lesson, "Bu dersi düzenleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        $view_data = [
            "lessonController" => new LessonController(),
            "lesson" => $lesson,
            "page_title" => $lesson->getFullName(true) . " Düzenle",
            "departments" => (new DepartmentRepository())->getActiveDepartments(),
            "units" => (new UnitRepository())->getAllUnits(),
            "department_programs" => (new DepartmentRepository())->getDepartmentProgramsList($lesson->department_id ?? null),
            "programController" => new ProgramController(),
            "classroomTypes" => ClassroomType::toArray(),
            "buildings" => (new BuildingRepository())->getAllBuildings()
        ];
        
        if ($currentUser->role == "department_head") {
            $view_data['lecturers'] = (new UserRepository())->getLecturersForDepartmentHead($currentUser->department_id);
            $view_data['lecturers'][] = clone (new UserRepository())->find($lesson->lecturer_id);
        } elseif ($currentUser->role == "lecturer") {
            $view_data['lecturers'][] = clone (new UserRepository())->find($lesson->lecturer_id);
        } else {
            $view_data['lecturers'] = (new UserRepository())->getAllLecturers();
        }
        return $view_data;
    }

    public function getImportLessonsPageData(AssetManager $assetManager): array
    {
        Gate::authorizeRole("submanager", false, "Ders İçe aktarma yetkiniz yok");
        $assetManager->loadPageAssets('importpages');
        return [
            "page_title" => " Ders İçe aktar",
        ];
    }

    /**
     * @throws Exception
     */
    public function getClassroomPageData(AssetManager $assetManager, $id = null): array
    {
        Gate::authorizeRole("submanager", false, "Derslik sayfasını görme yetkiniz yok");
        if (!is_null($id)) {
            $classroom = (new ClassroomRepository())->findClassroomWithSchedules($id) ?: throw new Exception("Derslik bulunamadı");
        } else {
            throw new Exception("Derslik id Numarası belirtilmemiş");
        }
        $assetManager->loadPageAssets('classroompage');
        return [
            "classroom" => $classroom,
            "page_title" => $classroom->name . " Sayfası",
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::CLASSROOM->value,
                    'owner_id' => $classroom->id,
                    'type' => 'lesson',
                    'semester_no' => getSemesterNumbers()
                ],
                true
            ),
            "midtermScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::CLASSROOM->value,
                    'owner_id' => $classroom->id,
                    'type' => ExamType::MIDTERM->value,
                    'semester_no' => getSemesterNumbers()
                ],
                true
            ),
            "finalScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::CLASSROOM->value,
                    'owner_id' => $classroom->id,
                    'type' => ExamType::FINAL->value,
                    'semester_no' => getSemesterNumbers()
                ],
                true
            ),
            "makeupScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::CLASSROOM->value,
                    'owner_id' => $classroom->id,
                    'type' => ExamType::MAKEUP->value,
                    'semester_no' => getSemesterNumbers()
                ],
                true
            ),
        ];
    }

    public function getListClassroomsPageData(AssetManager $assetManager): array
    {
        Gate::authorizeRole("submanager", false, "Derslik listesini görme yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        return [
            "classroomController" => new ClassroomController(),
            "classrooms" => (new Classroom())->get()->with('building')->all(),
            "page_title" => "Derslik Listesi"
        ];
    }

    public function getAddClassroomPageData(AssetManager $assetManager): array
    {
        Gate::authorize("create", Classroom::class, "Yeni derslik ekleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "page_title"     => "Derslik Ekle",
            "classroomTypes" => ClassroomType::toArray(),
            "buildings"      => (new BuildingRepository())->getAllBuildings(),
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditClassroomPageData(AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $classroom = (new Classroom())->find($id) ?: throw new Exception("Derslik bulunamadı");
            Gate::authorize("update", $classroom, "Bu dersliği düzenleme yetkiniz yok");
        } else {
            throw new Exception("Derslik Bulunamadı");
        }
        $assetManager->loadPageAssets('formpages');
        return [
            "classroomController" => new ClassroomController(),
            "classroom"           => $classroom,
            "classroomTypes"      => ClassroomType::toArray(),
            "buildings"           => (new BuildingRepository())->getAllBuildings(),
            "page_title"          => $classroom->name . " Düzenle",
        ];
    }

    /**
     * @throws Exception
     */
    public function getDepartmentPageData(AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $department = (new DepartmentRepository())->findDepartmentWithDetails($id) ?: throw new Exception("Bölüm bulunamadı");
        } else {
            throw new Exception("İd belirtilmemiş");
        }
        Gate::authorize("view", $department, "Bu bölüm sayfasını görme yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        $assetManager->loadPageAssets('singlepages');
        return [
            "department" => $department,
            "page_title" => $department->name . " Sayfası"
        ];
    }

    public function getListDepartmentsPageData(AssetManager $assetManager): array
    {
        Gate::authorize("view", Department::class, "Bölümler listesini görmek için yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        return [
            "departments" => (new DepartmentRepository())->getAllDepartmentsWithChairperson(),
            "page_title" => "Bölüm Listesi"
        ];
    }

    public function getAddDepartmentPageData(AssetManager $assetManager): array
    {
        Gate::authorize("create", Department::class, "Yeni Bölüm ekleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "page_title" => "Bölüm Ekle",
            "units"      => (new UnitRepository())->getAllUnits(),
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditDepartmentPageData(AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $department = (new Department())->find($id) ?: throw new Exception("Bölüm bulunamadı");
            Gate::authorize("update", $department, "Bu bölümü düzenleme yetkiniz yok");
        } else {
            throw new Exception("Bölüm bulunamadı");
        }
        $assetManager->loadPageAssets('formpages');
        return [
            "departmentController" => new DepartmentController(),
            "department"           => $department,
            "page_title"           => ($department->name ?? '') . ' Düzenle',
            "units"                => (new UnitRepository())->getAllUnits(),
        ];
    }

    /**
     * @throws Exception
     */
    public function getProgramPageData(AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $program = (new ProgramRepository())->findProgramWithDetails($id) ?: throw new Exception("Program bulunamadı");
        } else {
            throw new Exception("Program id değeri belirtilmelidir");
        }
        Gate::authorize("view", $program, "Bu programı görüntülemek için yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        $assetManager->loadPageAssets('singlepages');

        return [
            "program" => $program,
            "page_title" => $program->name . " Sayfası",
            "scheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::PROGRAM->value,
                    'owner_id' => $program->id,
                    'type' => 'lesson',
                ],
                true,
                false
            ),
            "midtermScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::PROGRAM->value,
                    'owner_id' => $program->id,
                    'type' => ExamType::MIDTERM->value
                ],
                true,
                false
            ),
            "finalScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::PROGRAM->value,
                    'owner_id' => $program->id,
                    'type' => ExamType::FINAL->value
                ],
                true,
                false
            ),
            "makeupScheduleHTML" => (new ScheduleController())->getSchedulesHTML(
                [
                    'owner_type' => OwnerType::PROGRAM->value,
                    'owner_id' => $program->id,
                    'type' => ExamType::MAKEUP->value
                ],
                true,
                false
            ),
        ];
    }

    /**
     * @throws Exception
     */
    public function getListProgramsPageData(AssetManager $assetManager): array
    {
        Gate::authorize("view", Program::class, "Programlar listesini görmek için yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        return [
            "programs" => (new ProgramRepository())->getAllProgramsWithDepartment(),
            "page_title" => "Program Listesi",
        ];
    }

    public function getAddProgramPageData(AssetManager $assetManager, $department_id = null): array
    {
        Gate::authorize("create", Program::class, "Program ekleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "page_title" => "Program Ekle",
            "departments" => (new DepartmentRepository())->getActiveDepartments(),
            "units" => (new UnitRepository())->getAllUnits(),
            "department_id" => $department_id
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditProgramPageData(AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $program = (new Program())->find($id) ?: throw new Exception("Program bulunamadı");
        } else {
            throw new Exception("Program id numarası belirtilmelidir");
        }
        Gate::authorize("update", $program, "Program düzenleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "programController" => new ProgramController(),
            "program" => $program,
            "departments" => (new DepartmentRepository())->getActiveDepartments(),
            "units" => (new UnitRepository())->getAllUnits(),
            "page_title" => $program->name ?? "" . " Düzenle",
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditSchedulePageData(User $currentUser, AssetManager $assetManager, $department_id = null): array
    {
        Gate::authorizeRole("department_head", false, "Ders programı düzenleme yetkiniz yok");
        $assetManager->loadPageAssets('editschedule');
        if (Gate::allowsRole("submanager")) {
            $departments = (new DepartmentRepository())->getActiveDepartments();
        } elseif (Gate::allowsRole("department_head") && $currentUser->role == "department_head") {
            /**
             * @var Department|null
             */
            $dep = (new DepartmentRepository())->find($currentUser->department_id);
            if (!$dep || !$dep->active) throw new Exception("Bölüm başkanının bölüm bilgisi yok veya bölüm pasif");
            $departments = [$dep];
        } else {
            throw new Exception("Bu işlem için yetkiniz yok");
        }
        $view_data = [
            "scheduleController" => new ScheduleController(),
            "departments" => $departments,
            "units" => (new UnitRepository())->getAllUnits(),
            "page_title" => "Ders Programı Düzenle",
            "classrooms" => (new ClassroomRepository())->findAll()
        ];
        if ($currentUser->role == "department_head") {
            $view_data['lecturers'] = (new UserRepository())->getLecturersForDepartmentHead($currentUser->department_id);
        } else {
            $view_data['lecturers'] = (new UserRepository())->getAllLecturers();
        }
        return $view_data;
    }

    /**
     * @throws Exception
     */
    public function getEditExamSchedulePageData(User $currentUser, AssetManager $assetManager, $department_id = null): array
    {
        Gate::authorizeRole("department_head", false, "Sınav programı düzenleme yetkiniz yok");
        $assetManager->loadPageAssets('editexamschedule');
        if (Gate::allowsRole("submanager")) {
            $departments = (new DepartmentRepository())->getActiveDepartments();
        } elseif (Gate::allowsRole("department_head") && $currentUser->role == "department_head") {
            /**
             * @var Department|null
             */
            $dep = (new DepartmentRepository())->find($currentUser->department_id);
            if (!$dep || !$dep->active) throw new Exception("Bölüm başkanının bölüm bilgisi yok veya bölüm pasif");
            $departments = [$dep];
        } else {
            throw new Exception("Bu işlem için yetkiniz yok");
        }
        $view_data = [
            "scheduleController" => new ScheduleController(),
            "departments" => $departments,
            "units" => (new UnitRepository())->getAllUnits(),
            "page_title" => "Sınav Programını Düzenle",
            "classrooms" => (new ClassroomRepository())->findAll()
        ];
        if ($currentUser->role == "department_head") {
            $view_data['lecturers'] = (new User())->get()->where(['department_id' => $currentUser->department_id, '!role' => ['admin', 'user']])->all();
        } else {
            $view_data['lecturers'] = (new User())->get()->where(['!role' => ["in" => ['admin', 'user']]])->all();
        }
        return $view_data;
    }

    /**
     * @throws Exception
     */
    public function getExportSchedulePageData(User $currentUser, AssetManager $assetManager): array
    {
        Gate::authorizeRole("department_head", false, "Ders programı Dışa aktarma yetkiniz yok");
        $assetManager->loadPageAssets('exportschedule');
        if (Gate::allowsRole("submanager")) {
            $departments = (new DepartmentRepository())->getActiveDepartments();
        } elseif (Gate::allowsRole("department_head") && $currentUser->role == "department_head") {
            $dep = (new DepartmentRepository())->find($currentUser->department_id);
            if (!$dep) throw new Exception("Bölüm bulunamadı");
            $departments = [$dep];
        } else {
            throw new Exception("Bu işlem için yetkiniz yok");
        }
        $view_data = [
            "scheduleController" => new ScheduleController(),
            "departments" => $departments,
            "units" => (new UnitRepository())->getAllUnits(),
            "page_title" => "Program Dışa Aktar",
            "classrooms" => (new ClassroomRepository())->findAll()
        ];
        if ($currentUser->role == "department_head") {
            $view_data['lecturers'] = (new UserRepository())->findBy(['department_id' => $currentUser->department_id]);
        } else {
            $view_data['lecturers'] = (new UserRepository())->findBy([]);
        }
        return $view_data;
    }

    public function getSettingsPageData(AssetManager $assetManager): array
    {
        Gate::authorize("create", Setting::class, "Ayarları görüntüleme yetkiniz yok.");
        $assetManager->loadPageAssets('formpages');

        // Ayarları model üzerinden çekiyoruz
        $settings = (new Setting())->get()->all();

        // View'a gönderilecek veriler
        return [
            "settings"   => $settings,
            "page_title" => "Ayarlar",
        ];
    }

    public function getEditPermissionPageData(AssetManager $assetManager): array
    {
        Gate::authorizeRole("submanager", false, "Yetkileri düzenleme yetkiniz yok.");
        $assetManager->loadPageAssets('formpages');
        $assetManager->addJs('/assets/js/permissionsWizard.js'); // Sihirbaz JS dosyası

        return [
            "page_title" => "Yetkileri Düzenle",
            "users"      => (new UserRepository())->getAllUsersWithDetails(),
            "units"      => (new UnitRepository())->getAllUnits()
        ];
    }

    public function getLogsPageData(AssetManager $assetManager): array
    {
        Gate::authorizeRole("submanager", false, "Kayıtlara erişim yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        $logs = (new Log())->get()->orderBy('created_at', 'DESC')->limit(500)->all();
        return [
            "page_title" => "Kayıtlar",
            "logs"       => $logs,
        ];
    }

    /**
     * @throws Exception
     */
    public function downloadFile($filename): void
    {
        $filename = urldecode($filename);
        $filename = basename($filename);
        $filePath = $_ENV["DOWNLOAD_PATH"] . "/" . $filename;
        if (!file_exists($filePath)) {
            if ($_ENV["DEBUG"]) {
                error_log(__LINE__ . ". satırda filePath değişkeni:" . var_export($filePath, true));
            }
            throw new Exception("İndirilecek dosya bulunamadı", 404);
        }

        $realPath = realpath($filePath);
        $baseDir  = realpath($_ENV["DOWNLOAD_PATH"]);
        if (!str_starts_with($realPath, $baseDir)) {
            throw new Exception("Bu dosyaya erişim izniniz yok.", 403);
        }

        $filename        = basename($filePath);
        $encodedFilename = rawurlencode($filename);
        $fileSize        = filesize($filePath);
        $fileType        = mime_content_type($filePath);

        header("Content-Description: File Transfer");
        header("Content-Type: " . $fileType);
        header("Content-Disposition: attachment; filename=\"$filename\"; filename*=UTF-8''$encodedFilename");
        header("Content-Length: " . $fileSize);
        header("Cache-Control: must-revalidate");
        header("Pragma: public");
        header("Expires: 0");

        readfile($filePath);
        exit;
    }

    // =========================================================
    // Birim (Unit) Sayfaları
    // =========================================================

    public function getListUnitsPageData(AssetManager $assetManager): array
    {
        Gate::authorize('view', Unit::class, 'Birim listesini görme yetkiniz yok');
        $assetManager->loadPageAssets('listpages');
        return [
            'units'      => (new UnitRepository())->getAllUnits(),
            'unitTypes'  => UnitType::toArray(),
            'page_title' => 'Birim Listesi',
        ];
    }

    public function getAddUnitPageData(AssetManager $assetManager): array
    {
        Gate::authorize('create', Unit::class, 'Yeni birim ekleme yetkiniz yok');
        $assetManager->loadPageAssets('formpages');
        return [
            'page_title' => 'Birim Ekle',
            'unitTypes'  => UnitType::toArray(),
        ];
    }

    /**
     * @throws Exception
     */
    public function getUnitPageData(AssetManager $assetManager, $id = null): array
    {
        if (is_null($id)) {
            throw new Exception('Birim ID belirtilmeli.');
        }
        $unit = (new UnitRepository())->findUnitWithDetails($id)
            ?: throw new Exception('Birim bulunamadı.');

        Gate::authorize('view', $unit, 'Bu birimi görme yetkiniz yok');
        $assetManager->loadPageAssets('listpages');
        $assetManager->loadPageAssets('singlepages');
        return [
            'unit'       => $unit,
            'page_title' => $unit->name . ' Sayfası',
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditUnitPageData(AssetManager $assetManager, $id = null): array
    {
        if (is_null($id)) {
            throw new Exception('Birim bulunamadı.');
        }
        $unit = (new Unit())->find($id) ?: throw new Exception('Birim bulunamadı.');
        Gate::authorize('update', $unit, 'Bu birimi düzenleme yetkiniz yok');
        $assetManager->loadPageAssets('formpages');
        return [
            'unit'       => $unit,
            'unitTypes'  => UnitType::toArray(),
            'page_title' => ($unit->name ?? '') . ' Düzenle',
        ];
    }

    // =========================================================
    // Bina (Building) Sayfaları
    // =========================================================

    public function getListBuildingsPageData(AssetManager $assetManager): array
    {
        Gate::authorize('view', Building::class, 'Bina listesini görme yetkiniz yok');
        $assetManager->loadPageAssets('listpages');
        return [
            'buildings'  => (new BuildingRepository())->getAllBuildings(),
            'page_title' => 'Bina Listesi',
        ];
    }

    public function getAddBuildingPageData(AssetManager $assetManager): array
    {
        Gate::authorize('create', Building::class, 'Yeni bina ekleme yetkiniz yok');
        $assetManager->loadPageAssets('formpages');
        return [
            'page_title' => 'Bina Ekle',
        ];
    }

    /**
     * @throws Exception
     */
    public function getBuildingPageData(AssetManager $assetManager, $id): array
    {
        if (is_null($id)) {
            throw new Exception('Bina bulunamadı.');
        }

        $building = (new Building())->with('classrooms')->find($id);
        if (!$building) {
            throw new Exception('Bina bulunamadı.');
        }

        Gate::authorize('view', $building, 'Bina detayını görme yetkiniz yok');
        
        return [
            'page_title' => $building->name,
            'building'   => $building,
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditBuildingPageData(AssetManager $assetManager, $id = null): array
    {
        if (is_null($id)) {
            throw new Exception('Bina bulunamadı.');
        }
        $building = (new Building())->find($id) ?: throw new Exception('Bina bulunamadı.');
        Gate::authorize('update', $building, 'Bu binayı düzenleme yetkiniz yok');
        $assetManager->loadPageAssets('formpages');
        return [
            'building'   => $building,
            'page_title' => ($building->name ?? '') . ' Düzenle',
        ];
    }
}
