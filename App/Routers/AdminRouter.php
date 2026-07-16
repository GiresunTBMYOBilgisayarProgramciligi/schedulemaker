<?php
/**
 * Admin paneli sayfalarını yöneten dosya
 * Görevleri:
 * 1- Oturum kontrolü yapmak
 * 2- Uygun kontrolcüye yönlendirme yapmak
 * 3- View verilerini hazırlamak 
 * 4- View döndürmek
 */

namespace App\Routers;

use App\Controllers\AdminPageController;
use App\Middlewares\AuthMiddleware;
use App\Attributes\AuthRequired;
use App\Core\Router;
use App\Models\User;
use Exception;

/**
 * AdminRouter Sınıfı
 * /admin altında gelen istekleri yönetir.
 */
#[AuthRequired]
class AdminRouter extends Router
{
    private ?User $currentUser = null;
    private AdminPageController $pageController;

    public function __construct()
    {
        parent::__construct();
        $this->currentUser = AuthMiddleware::user();
        $this->view_data['currentUser'] = $this->currentUser;
        $this->pageController = new AdminPageController();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function IndexAction(): void
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getIndexPageData($this->currentUser, $this->assetManager));
        $this->callView("admin/index/index");
    }

    /*
     * User Routes
     */
    public function ListUsersAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getListUsersPageData($this->currentUser, $this->assetManager));
        $this->callView("admin/users/listusers");
    }

    /**
     * @throws Exception
     */
    public function AddUserAction(?int $department_id = null, ?int $program_id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getAddUserPageData($this->assetManager, $department_id, $program_id));
        $this->callView("admin/users/adduser");
    }

    /**
     * @throws Exception
     */
    public function ProfileAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getProfilePageData($this->currentUser, $this->assetManager, $id));
        $this->callView("admin/users/profile");
    }

    /**
     * @throws Exception
     */
    public function EditUserAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getEditUserPageData($this->currentUser, $this->assetManager, $id));
        $this->callView("admin/users/edituser");
    }

    public function importUsersAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getImportUsersPageData($this->assetManager));
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
        $this->view_data = array_merge($this->view_data, $this->pageController->getLessonPageData($this->assetManager, $id));
        $this->callView("admin/lessons/lesson");
    }

    public function ListLessonsAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getListLessonsPageData($this->currentUser, $this->assetManager));
        $this->callView("admin/lessons/listlessons");
    }

    public function AddLessonAction(?int $program_id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getAddLessonPageData($this->currentUser, $this->assetManager, $program_id));
        $this->callView("admin/lessons/addlesson");
    }

    /**
     * @throws Exception
     */
    public function EditLessonAction($id = null): void
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getEditLessonPageData($this->currentUser, $this->assetManager, $id));
        $this->callView("admin/lessons/editlesson");
    }

    public function importLessonsAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getImportLessonsPageData($this->assetManager));
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
        $this->view_data = array_merge($this->view_data, $this->pageController->getClassroomPageData($this->assetManager, $id));
        $this->callView("admin/classrooms/classroom");
    }

    public function ListClassroomsAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getListClassroomsPageData($this->assetManager));
        $this->callView("admin/classrooms/listclassrooms");
    }

    public function AddClassroomAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getAddClassroomPageData($this->assetManager));
        $this->callView("admin/classrooms/addclassroom");
    }

    /**
     * @throws Exception
     */
    public function editClassroomAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getEditClassroomPageData($this->assetManager, $id));
        $this->callView("admin/classrooms/editclassroom");
    }

    /*
     * Department Routes
     */
    public function departmentAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getDepartmentPageData($this->assetManager, $id));
        $this->callView("admin/departments/department");
    }

    public function ListDepartmentsAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getListDepartmentsPageData($this->assetManager));
        $this->callView("admin/departments/listdepartments");
    }

    public function AddDepartmentAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getAddDepartmentPageData($this->assetManager));
        $this->callView("admin/departments/adddepartment");
    }

    /**
     * @throws Exception
     */
    public function editDepartmentAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getEditDepartmentPageData($this->assetManager, $id));
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
        $this->view_data = array_merge($this->view_data, $this->pageController->getProgramPageData($this->assetManager, $id));
        $this->callView("admin/programs/program");
    }

    /**
     * @return void
     * @throws Exception
     */
    public function ListProgramsAction(): void
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getListProgramsPageData($this->assetManager));
        $this->callView("admin/programs/listprograms");
    }

    public function AddProgramAction($department_id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getAddProgramPageData($this->assetManager, $department_id));
        $this->callView("admin/programs/addprogram");
    }

    public function editProgramAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getEditProgramPageData($this->assetManager, $id));
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
        $this->view_data = array_merge($this->view_data, $this->pageController->getEditSchedulePageData($this->currentUser, $this->assetManager, $department_id));
        $this->callView("admin/schedules/editschedule");
    }

    public function EditExamScheduleAction($department_id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getEditExamSchedulePageData($this->currentUser, $this->assetManager, $department_id));
        $this->callView("admin/schedules/editexamschedule");
    }

    /**
     * @throws Exception
     */
    public function exportScheduleAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getExportSchedulePageData($this->currentUser, $this->assetManager));
        $this->callView("admin/schedules/exportschedule");
    }

    /*
     * Ayarlar
     */
    public function SettingsAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getSettingsPageData($this->assetManager));
        $this->callView("admin/settings/settings");
    }

    public function LogsAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getLogsPageData($this->assetManager));
        $this->callView("admin/settings/logs");
    }

    /**
     * @throws Exception
     */
    public function downloadAction($filename)
    {
        $this->pageController->downloadFile($filename);
    }

    /*
     * Unit Routes (Birimler: Fakülte, MYO, Enstitü vb.)
     */
    public function ListUnitsAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getListUnitsPageData($this->assetManager));
        $this->callView('admin/units/listunits');
    }

    public function AddUnitAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getAddUnitPageData($this->assetManager));
        $this->callView('admin/units/addunit');
    }

    public function unitAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getUnitPageData($this->assetManager, $id));
        $this->callView('admin/units/unit');
    }

    public function editUnitAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getEditUnitPageData($this->assetManager, $id));
        $this->callView('admin/units/editunit');
    }

    /*
     * Building Routes (Binalar)
     */
    public function ListBuildingsAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getListBuildingsPageData($this->assetManager));
        $this->callView('admin/buildings/listbuildings');
    }

    public function AddBuildingAction()
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getAddBuildingPageData($this->assetManager));
        $this->callView('admin/buildings/addbuilding');
    }

    public function buildingAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getBuildingPageData($this->assetManager, $id));
        $this->callView('admin/buildings/building');
    }

    public function editBuildingAction($id = null)
    {
        $this->view_data = array_merge($this->view_data, $this->pageController->getEditBuildingPageData($this->assetManager, $id));
        $this->callView('admin/buildings/editbuilding');
    }
}