<?php

namespace App\Routers;

use App\Repositories\UserRepository;
use App\Middlewares\AuthMiddleware;
use App\Core\AssetManager;
use App\Core\Router;
use App\Enums\ExamType;
use App\Models\Building;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Unit;
use App\Models\User;
use Exception;

/**
 * HomeRouter Sınıfı
 * Sistemin genel (public) anasayfasını yönetir.
 * 
 * Not: Projenin genelinde uygulanan "Single Responsibility Principle" (Tek Sorumluluk Prensibi) 
 * gereği business logic'lerin (iş kurallarının) Controller sınıflarına taşınması mimarisine rağmen, 
 * bu sınıf sadece tek bir eylem (IndexAction) barındırdığından ve karmaşık bir iş kuralı içermediğinden 
 * şimdilik olduğu gibi bırakılmıştır. Gelecekte anasayfa işlemleri karmaşıklaşırsa HomePageController 
 * gibi bir yapıya taşınabilir.
 */
class HomeRouter extends Router
{
    private ?User $currentUser = null;

    public function __construct()
    {
        parent::__construct();
        $this->currentUser = AuthMiddleware::user();
        $this->view_data['currentUser'] = $this->currentUser;
    }

    /**
     * @throws \Exception
     */
    public function IndexAction()
    {
        $userRepository = new UserRepository();
        $this->assetManager->loadPageAssets("homeIndex");

        // 1. Program parametreleri
        $selectedUnitId = (string)($_GET['unit_id'] ?? $_GET['unit'] ?? '');
        $selectedDepartmentId = (string)($_GET['department_id'] ?? $_GET['department'] ?? '');
        $selectedProgramId = (string)($_GET['program_id'] ?? $_GET['program'] ?? '');
        $selectedSemesterNo = (string)($_GET['semester_no'] ?? $_GET['semesterNo'] ?? '');

        // Otomatik hiyerarşi tamamlama (Program için)
        if ($selectedProgramId !== '' && $selectedDepartmentId === '') {
            try {
                $prog = (new Program())->find((int)$selectedProgramId);
                if ($prog && !empty($prog->department_id)) {
                    $selectedDepartmentId = (string)$prog->department_id;
                }
            } catch (\Throwable) {}
        }
        if ($selectedDepartmentId !== '' && $selectedUnitId === '') {
            try {
                $dept = (new Department())->find((int)$selectedDepartmentId);
                if ($dept && !empty($dept->unit_id)) {
                    $selectedUnitId = (string)$dept->unit_id;
                }
            } catch (\Throwable) {}
        }

        // 2. Hoca (Lecturer) parametreleri
        $selectedLecturerId = (string)($_GET['lecturer_id'] ?? $_GET['lecturer'] ?? $_GET['lecturerId'] ?? $_GET['user_id'] ?? $_GET['user'] ?? '');
        $selectedLecturerUnitId = (string)($_GET['lecturer_unit_id'] ?? $_GET['lecturer_unit'] ?? $_GET['lecturerUnit'] ?? '');

        if ($selectedLecturerId !== '') {
            if ($selectedLecturerUnitId === '' && $selectedUnitId !== '') {
                $selectedLecturerUnitId = $selectedUnitId;
            }
            if ($selectedLecturerUnitId === '') {
                try {
                    $lecturerUser = (new User())->find((int)$selectedLecturerId);
                    if ($lecturerUser) {
                        if (!empty($lecturerUser->unit_id)) {
                            $selectedLecturerUnitId = (string)$lecturerUser->unit_id;
                        } elseif (!empty($lecturerUser->department_id)) {
                            $dept = (new Department())->find((int)$lecturerUser->department_id);
                            if ($dept && !empty($dept->unit_id)) {
                                $selectedLecturerUnitId = (string)$dept->unit_id;
                            }
                        } else {
                            $affiliations = $lecturerUser->getAffiliations();
                            foreach ($affiliations as $aff) {
                                if (!empty($aff->unit_id)) {
                                    $selectedLecturerUnitId = (string)$aff->unit_id;
                                    break;
                                }
                            }
                        }
                    }
                } catch (\Throwable) {}
            }
        }

        // 3. Derslik (Classroom) parametreleri
        $selectedClassroomId = (string)($_GET['classroom_id'] ?? $_GET['classroom'] ?? $_GET['classroomId'] ?? '');
        $selectedClassroomBuildingId = (string)($_GET['classroom_building_id'] ?? $_GET['building_id'] ?? $_GET['building'] ?? $_GET['classroomBuildingId'] ?? '');
        $selectedClassroomUnitId = (string)($_GET['classroom_unit_id'] ?? $_GET['classroom_unit'] ?? $_GET['classroomUnit'] ?? '');

        if ($selectedClassroomId !== '') {
            if ($selectedClassroomBuildingId === '') {
                try {
                    $classroomObj = (new Classroom())->find((int)$selectedClassroomId);
                    if ($classroomObj && !empty($classroomObj->building_id)) {
                        $selectedClassroomBuildingId = (string)$classroomObj->building_id;
                    }
                } catch (\Throwable) {}
            }
        }

        if ($selectedClassroomBuildingId !== '' && $selectedClassroomUnitId === '') {
            if ($selectedUnitId !== '') {
                $selectedClassroomUnitId = $selectedUnitId;
            } else {
                try {
                    $buildingObj = (new Building())->find((int)$selectedClassroomBuildingId);
                    if ($buildingObj && !empty($buildingObj->unit_id)) {
                        $selectedClassroomUnitId = (string)$buildingObj->unit_id;
                    }
                } catch (\Throwable) {}
            }
        }

        // 4. Aktif Tab tespiti
        $requestedTab = strtolower((string)($_GET['tab'] ?? ''));
        if (in_array($requestedTab, ['program', 'lecturer', 'classroom'], true)) {
            $activeTab = $requestedTab;
        } elseif ($selectedClassroomId !== '' || $selectedClassroomBuildingId !== '') {
            $activeTab = 'classroom';
        } elseif ($selectedLecturerId !== '') {
            $activeTab = 'lecturer';
        } elseif ($requestedTab === 'classroom' || (!empty($_GET['classroom_unit_id']) || !empty($_GET['classroom_unit']))) {
            $activeTab = 'classroom';
        } elseif ($requestedTab === 'lecturer' || (!empty($_GET['lecturer_unit_id']) || !empty($_GET['lecturer_unit']))) {
            $activeTab = 'lecturer';
        } else {
            $activeTab = 'program';
        }

        // 5. Genel parametreler
        // Sınav türleri kısa adlarla da verilebilir (?type=vize, ?type=final, ?type=but); tanınmayan değer ders programına düşer
        $requestedScheduleType = (string)($_GET['schedule_type'] ?? $_GET['type'] ?? 'lesson');
        $selectedScheduleType = ExamType::fromAlias($requestedScheduleType)?->value ?? 'lesson';
        $selectedAcademicYear = isset($_GET['academic_year']) ? (string)$_GET['academic_year'] : (isset($_GET['year']) ? (string)$_GET['year'] : null);
        $selectedSemester = isset($_GET['semester']) ? (string)$_GET['semester'] : null;

        $this->view_data = array_merge($this->view_data, [
            "units" => (new Unit())->get()->where(['active' => true])->all(),
            "departments" => (new Department())->get()->where(['active' => true])->all(),
            "classrooms" => (new Classroom())->get()->all(),
            "lecturers" => $userRepository->getAllLecturers(),
            "selected_unit_id" => $selectedUnitId,
            "selected_department_id" => $selectedDepartmentId,
            "selected_program_id" => $selectedProgramId,
            "selected_semester_no" => $selectedSemesterNo,
            "selected_lecturer_id" => $selectedLecturerId,
            "selected_lecturer_unit_id" => $selectedLecturerUnitId,
            "selected_classroom_id" => $selectedClassroomId,
            "selected_classroom_building_id" => $selectedClassroomBuildingId,
            "selected_classroom_unit_id" => $selectedClassroomUnitId,
            "active_tab" => $activeTab,
            "selected_schedule_type" => $selectedScheduleType,
            "selected_academic_year" => $selectedAcademicYear,
            "selected_semester" => $selectedSemester,
            "page_title" => "Anasayfa"
        ]);
        $this->callView("home/index");
    }
}