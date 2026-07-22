<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\AssetManager;
use App\Core\Gate;
use App\Exceptions\AuthorizationException;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Log;
use App\Models\Program;
use App\Models\User;
use App\Models\Unit;
use App\Models\Building;
use App\Models\Setting;
use App\Repositories\ClassroomRepository;
use App\Repositories\DepartmentRepository;
use App\Repositories\UserRepository;
use App\Repositories\LessonRepository;
use App\Repositories\ProgramRepository;
use App\Repositories\UnitRepository;
use App\Repositories\BuildingRepository;
use App\Policies\BuildingPolicy;

use App\Enums\ClassroomType;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Enums\UnitType;
use App\Enums\PermissionType;
use App\Controllers\SettingsController;
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
        $assetManager->addJs("/assets/js/exportSchedule.js");
        
        return $view_data;
    }

    public function getListUsersPageData(User $currentUser, AssetManager $assetManager): array
    {
        Gate::authorize(PermissionType::LIST->value, User::class, "Kullanıcı listesini görme yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        $view_data = [
            "page_title" => "Kullanıcı Listesi",
        ];
        if ($currentUser->role == "department_head") {
            $view_data['users'] = (new UserRepository())->getUsersForDepartmentHead($currentUser->department_id);
        } else {
            $view_data['users'] = (new UserRepository())->getAuthorized('view', [], ['department', 'program', 'unit']);
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
            Gate::authorize(PermissionType::UPDATE->value, $department, "Bu bölüme yeni kullanıcı ekleme yetkiniz yok");
        }
        if ($program_id) {
            $program = (new Program())->find($program_id) ?: throw new Exception("Program bulunamadı");
            Gate::authorize(PermissionType::UPDATE->value, $program, "Bu programa yeni kullanıcı ekleme yetkiniz yok");
        }
        if (!(isset($department) or isset($program))) {
            Gate::authorize(PermissionType::CREATE->value, User::class, "Yeni kullanıcı ekleme yetkiniz yok");
        }
        $assetManager->loadPageAssets('formpages');
        return [
            "page_title" => "Kullanıcı Ekle",
            "userController" => new UserController(),
            "units" => (new UnitRepository())->getAuthorized('view'),
            "departments" => (new DepartmentRepository())->getAuthorized('view', ['active' => true]),
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

        Gate::authorize(PermissionType::VIEW->value, $user, "Bu profili görme yetkiniz yok");
        $assetManager->loadPageAssets('profilepage');
        $assetManager->loadPageAssets('formpages');
        

        return [
            "user" => $user,
            "canEditSpecialFields" => Gate::allowsRole('submanager') || ($currentUser->role === 'department_head' && $currentUser->id !== $user->id),
            "page_title" => $user->getFullName() . " Profil Sayfası",
            "userController" => new UserController(),
            "units" => (new UnitRepository())->getAuthorized('view'),
            "departments" => (new DepartmentRepository())->getAuthorized('view', ['active' => true]),
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
        Gate::authorize(PermissionType::UPDATE->value, $user, "Kullanıcı düzenleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "user" => $user,
            "page_title" => $user->getFullName() . " Kullanıcı Düzenle",
            "units" => (new UnitRepository())->getAuthorized('view'),
            "departments" => (new DepartmentRepository())->getAuthorized('view', ['active' => true]),
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
        Gate::authorize(PermissionType::VIEW->value, $lesson, "Bu dersi görme yetkiniz yok");
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
        Gate::authorize(PermissionType::LIST->value, Lesson::class, "Ders listesini görme yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        $view_data = [
            "lessonController" => new LessonController(),
            "page_title" => "Ders Listesi"
        ];
        if ($currentUser->role == "department_head") {
            $view_data['lessons'] = (new LessonRepository())->getLessonsForDepartmentHead($currentUser->department_id);
        } else {
            $view_data['lessons'] = (new LessonRepository())->getAuthorized('view', [], ['lecturer', 'program', 'department', 'building']);
        }
        return $view_data;
    }

    public function getAddLessonPageData(User $currentUser, AssetManager $assetManager, ?int $program_id = null): array
    {
        Gate::authorize(PermissionType::CREATE->value, Lesson::class, "Yeni ders ekleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        $view_data = [
            "page_title" => "Ders Ekle",
            "departments" => (new DepartmentRepository())->getAuthorized('view', ['active' => true]),
            "units" => (new UnitRepository())->getAuthorized('view'),
            "lessonController" => new LessonController(),
            "classroomTypes" => ClassroomType::toArray(),
            "buildings" => (new BuildingRepository())->getAuthorized('view', [], ['unit']),
            "program_id" => $program_id
        ];
        $view_data['lecturers'] = (new UserRepository())->getAuthorized('view', ['!role' => ["in" => ['admin', 'user']]]);
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
        Gate::authorize(PermissionType::UPDATE->value, $lesson, "Bu dersi düzenleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        $view_data = [
            "lessonController" => new LessonController(),
            "lesson" => $lesson,
            "page_title" => $lesson->getFullName(true) . " Düzenle",
            "departments" => (new DepartmentRepository())->getAuthorized('view', ['active' => true]),
            "units" => (new UnitRepository())->getAuthorized('view'),
            "department_programs" => (new DepartmentRepository())->getDepartmentProgramsList($lesson->department_id ?? null),
            "programController" => new ProgramController(),
            "classroomTypes" => ClassroomType::toArray(),
            "buildings" => (new BuildingRepository())->getAuthorized('view', [], ['unit'])
        ];
        
        $view_data['lecturers'] = (new UserRepository())->getAuthorized('view', ['!role' => ["in" => ['admin', 'user']]]);
        // Mevcut hocanın listede her zaman görünmesini sağla
        $currentLecturerIds = array_map(fn($l) => $l->id, $view_data['lecturers']);
        if (!in_array($lesson->lecturer_id, $currentLecturerIds)) {
            $view_data['lecturers'][] = clone (new UserRepository())->find($lesson->lecturer_id);
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
        Gate::authorize(PermissionType::LIST->value, Classroom::class, "Derslik listesini görme yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        return [
            "classroomController" => new ClassroomController(),
            "classrooms" => (new ClassroomRepository())->getAuthorized('view'),
            "page_title" => "Derslik Listesi"
        ];
    }

    public function getAddClassroomPageData(AssetManager $assetManager): array
    {
        Gate::authorize(PermissionType::CREATE->value, Classroom::class, "Yeni derslik ekleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "page_title"     => "Derslik Ekle",
            "classroomTypes" => ClassroomType::toArray(),
            "buildings"      => (new BuildingRepository())->getAuthorized('view'),
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditClassroomPageData(AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $classroom = (new Classroom())->find($id) ?: throw new Exception("Derslik bulunamadı");
            Gate::authorize(PermissionType::UPDATE->value, $classroom, "Bu dersliği düzenleme yetkiniz yok");
        } else {
            throw new Exception("Derslik Bulunamadı");
        }
        $assetManager->loadPageAssets('formpages');
        return [
            "classroomController" => new ClassroomController(),
            "classroom"           => $classroom,
            "classroomTypes"      => ClassroomType::toArray(),
            "buildings"           => (new BuildingRepository())->getAuthorized('view'),
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
        Gate::authorize(PermissionType::VIEW->value, $department, "Bu bölüm sayfasını görme yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        $assetManager->loadPageAssets('singlepages');
        return [
            "department" => $department,
            "page_title" => $department->name . " Sayfası"
        ];
    }

    public function getListDepartmentsPageData(AssetManager $assetManager): array
    {
        Gate::authorize(PermissionType::LIST->value, Department::class, "Bölümler listesini görmek için yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        return [
            "departments" => (new DepartmentRepository())->getAuthorized('view', [], ['chairperson', 'unit']),
            "page_title" => "Bölüm Listesi"
        ];
    }

    public function getAddDepartmentPageData(AssetManager $assetManager): array
    {
        Gate::authorize(PermissionType::CREATE->value, Department::class, "Yeni Bölüm ekleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "page_title" => "Bölüm Ekle",
            "units"      => (new UnitRepository())->getAuthorized('view'),
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditDepartmentPageData(AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $department = (new Department())->find($id) ?: throw new Exception("Bölüm bulunamadı");
            Gate::authorize(PermissionType::UPDATE->value, $department, "Bu bölümü düzenleme yetkiniz yok");
        } else {
            throw new Exception("Bölüm bulunamadı");
        }
        $assetManager->loadPageAssets('formpages');
        return [
            "departmentController" => new DepartmentController(),
            "department"           => $department,
            "page_title"           => ($department->name ?? '') . ' Düzenle',
            "units"                => (new UnitRepository())->getAuthorized('view'),
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
        Gate::authorize(PermissionType::VIEW->value, $program, "Bu programı görüntülemek için yetkiniz yok");
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
        Gate::authorize(PermissionType::LIST->value, Program::class, "Programlar listesini görmek için yetkiniz yok");
        $assetManager->loadPageAssets('listpages');
        return [
            "programs" => (new ProgramRepository())->getAuthorized('view', [], ['department']),
            "page_title" => "Program Listesi",
        ];
    }

    public function getAddProgramPageData(AssetManager $assetManager, $department_id = null): array
    {
        Gate::authorize(PermissionType::CREATE->value, Program::class, "Program ekleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "page_title" => "Program Ekle",
            "departments" => (new DepartmentRepository())->getAuthorized('view', ['active' => true]),
            "units" => (new UnitRepository())->getAuthorized('view'),
            "department_id" => $department_id
        ];
    }

    /**
     * @throws Exception
     */
    public function getEditProgramPageData(AssetManager $assetManager, $id = null): array
    {
        if (!is_null($id)) {
            $program = (new Program())->get()->with(['department'])->find($id) ?: throw new Exception("Program bulunamadı");
        } else {
            throw new Exception("Program id numarası belirtilmelidir");
        }
        Gate::authorize(PermissionType::UPDATE->value, $program, "Program düzenleme yetkiniz yok");
        $assetManager->loadPageAssets('formpages');
        return [
            "programController" => new ProgramController(),
            "program" => $program,
            "departments" => (new DepartmentRepository())->getAuthorized('view', ['active' => true]),
            "units" => (new UnitRepository())->getAuthorized('view'),
            "page_title" => $program->name ?? "" . " Düzenle",
        ];
    }



    /**
     * @throws Exception
     */
    public function getEditSchedulePageData(User $currentUser, AssetManager $assetManager, $department_id = null): array
    {
        $assetManager->loadPageAssets('editschedule');
        
        $departments = (new DepartmentRepository())->getAuthorized('manage_schedule', ['active' => true]);
        
        if (empty($departments)) {
            throw new AuthorizationException("Ders programı düzenleme yetkiniz yok", [], 403);
        }
        $view_data = [
            "scheduleController" => new ScheduleController(),
            "departments" => $departments,
            "units" => (new UnitRepository())->getAuthorized('view', ['active' => true]),
            "page_title" => "Ders Programı Düzenle",
            "classrooms" => (new ClassroomRepository())->getAuthorized('view', [], ['building'])
        ];
        $view_data['lecturers'] = (new UserRepository())->getAuthorized('view', ['!role' => ['admin', 'user']]);
        return $view_data;
    }

    /**
     * @throws Exception
     */
    public function getEditExamSchedulePageData(User $currentUser, AssetManager $assetManager, $department_id = null): array
    {
        $assetManager->loadPageAssets('editexamschedule');
        
        $departments = (new DepartmentRepository())->getAuthorized('manage_schedule', ['active' => true]);

        if (empty($departments)) {
            throw new AuthorizationException("Sınav programı düzenleme yetkiniz yok", [], 403);
        }
        $view_data = [
            "scheduleController" => new ScheduleController(),
            "departments" => $departments,
            "units" => (new UnitRepository())->getAuthorized('view', ['active' => true]),
            "page_title" => "Sınav Programını Düzenle",
            "classrooms" => (new ClassroomRepository())->getAuthorized('view', [], ['building'])
        ];
        if (Gate::allowsRole("submanager")) {
            $view_data['lecturers'] = (new User())->get()->where(['!role' => ["in" => ['admin', 'user']]])->all();
        } else {
            $deptIds = array_column($departments, 'id');
            $view_data['lecturers'] = (new User())->get()->where(['department_id' => ['in' => $deptIds], '!role' => ['admin', 'user']])->all();
        }
        return $view_data;
    }

    /**
     * @throws Exception
     */
    public function getExportSchedulePageData(User $currentUser, AssetManager $assetManager): array
    {
        $assetManager->loadPageAssets('exportschedule');
        
        $units       = (new UnitRepository())->getAuthorized('view', ['active' => true]);
        $departments = (new DepartmentRepository())->getAuthorized('view', ['active' => true]);

        if (empty($departments) && empty($units)) {
            throw new AuthorizationException("Ders programı Dışa aktarma yetkiniz yok", [], 403);
        }

        $view_data = [
            "scheduleController" => new ScheduleController(),
            "units"              => $units,
            "departments"        => $departments,
            "page_title"         => "Program Dışa Aktar",
            "classrooms"         => (new ClassroomRepository())->getAuthorized('view', [], ['building']),
            "lecturers"          => (new UserRepository())->getAuthorized('view', ['!role' => ['in' => ['admin', 'user']]])
        ];

        return $view_data;
    }

    public function getSettingsPageData(AssetManager $assetManager): array
    {
        Gate::authorize(PermissionType::CREATE->value, Setting::class, "Ayarları görüntüleme yetkiniz yok.");
        $assetManager->loadPageAssets('formpages');

        // Ayarları model üzerinden çekiyoruz (SettingsController getSettings metodu ile formatlanmış halde)
        $settings = (new SettingsController())->getSettings();

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
            "units"      => (new UnitRepository())->getAuthorized('view')
        ];
    }

    public function getLogsPageData(AssetManager $assetManager): array
    {
        Gate::authorizeRole("admin", false, "Kayıtlara erişim yetkiniz yok");
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
        Gate::authorize(PermissionType::LIST->value, Unit::class, 'Birim listesini görme yetkiniz yok');
        $assetManager->loadPageAssets('listpages');
        return [
            'units'      => (new UnitRepository())->getAuthorized('view'),
            'unitTypes'  => UnitType::toArray(),
            'page_title' => 'Birim Listesi',
        ];
    }

    public function getAddUnitPageData(AssetManager $assetManager): array
    {
        Gate::authorize(PermissionType::CREATE->value, Unit::class, 'Yeni birim ekleme yetkiniz yok');
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

        Gate::authorize(PermissionType::VIEW->value, $unit, 'Bu birimi görme yetkiniz yok');
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
        Gate::authorize(PermissionType::UPDATE->value, $unit, 'Bu birimi düzenleme yetkiniz yok');
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
        Gate::authorize(PermissionType::LIST->value, Building::class, 'Bina listesini görme yetkiniz yok');
        $assetManager->loadPageAssets('listpages');

        return [
            'buildings'  => (new BuildingRepository())->getAuthorized('view'),
            'page_title' => 'Bina Listesi',
        ];
    }

    public function getAddBuildingPageData(AssetManager $assetManager): array
    {
        Gate::authorize(PermissionType::CREATE->value, Building::class, 'Yeni bina ekleme yetkiniz yok');
        $assetManager->loadPageAssets('formpages');

        $units = (new UnitRepository())->getAuthorized('view');

        return [
            'page_title' => 'Bina Ekle',
            'units'      => $units,
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

        Gate::authorize(PermissionType::VIEW->value, $building, 'Bina detayını görme yetkiniz yok');
        
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
        Gate::authorize(PermissionType::UPDATE->value, $building, 'Bu binayı düzenleme yetkiniz yok');
        $assetManager->loadPageAssets('formpages');

        $units = (new UnitRepository())->getAuthorized('view');

        return [
            'building'   => $building,
            'units'      => $units,
            'page_title' => ($building->name ?? '') . ' Düzenle',
        ];
    }
}
