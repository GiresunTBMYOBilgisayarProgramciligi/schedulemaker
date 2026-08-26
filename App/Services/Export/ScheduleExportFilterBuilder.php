<?php

namespace App\Services\Export;

use App\Enums\ExamType;
use App\Validators\Schedule\ScheduleExportFilterValidator;
use App\Models\Department;
use App\Models\Unit;
use App\Models\Building;
use App\Models\Program;
use App\Models\User;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Middlewares\AuthMiddleware;

use App\Enums\OwnerType;
use App\Repositories\UnitRepository;
use App\Repositories\BuildingRepository;
use App\Repositories\DepartmentRepository;
use App\Repositories\ProgramRepository;
use App\Repositories\UserRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\LessonRepository;
use Exception;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSemesterNumbers;

/**
 * Export isteğinden, Schedule sorgulama filtrelerinin listesini üretir.
 *
 * Çıktı dizisinin her elemanı şu yapıdadır:
 * [
 *   'file_title' => string,   // İndirilen dosyanın adı için kullanılır
 *   'title'      => string,   // Excel/ICS içindeki program başlığı
 *   'type'       => string,   // 'program'|'user'|'classroom'|'lesson'  (filtre türü)
 *   'filter'     => array,    // Schedule::where() için kullanılacak filtre dizisi
 * ]
 */
class ScheduleExportFilterBuilder
{
    /**
     * @param array $filters Doğrulanmış filtre dizisi (ScheduleExportFilterValidator'dan geçmiş)
     * @return array Filtre listesi
     * @throws Exception
     */
    public function build(array $filters): array
    {
        $filters         = (new ScheduleExportFilterValidator())->sanitize($filters, "generateScheduleFilters");
        $scheduleFilters = [];
        $semesterNumbers = !empty($filters["semester_no"]) ? [(int)$filters["semester_no"]] : getSemesterNumbers($filters["semester"]);
        $typeKey         = $filters["type"];
        $typeLabel       = $this->getTypeLabel($typeKey);

        switch ($filters["owner_type"]) {
            case OwnerType::PROGRAM->value:
                $scheduleFilters = $this->buildForProgram($filters, $semesterNumbers, $typeKey, $typeLabel);
                break;

            case "department":
                $scheduleFilters = $this->buildForDepartment($filters, $typeKey, $typeLabel);
                break;

            case "unit":
                $scheduleFilters = $this->buildForUnit($filters, $typeKey, $typeLabel);
                break;

            case "building":
                $scheduleFilters = $this->buildForBuilding($filters, $typeKey, $typeLabel);
                break;

            case "classroom_unit":
                $scheduleFilters = $this->buildForClassroomUnit($filters, $typeKey, $typeLabel);
                break;

            case OwnerType::USER->value:
                $scheduleFilters = $this->buildForUser($filters, $typeKey, $typeLabel);
                break;

            case OwnerType::CLASSROOM->value:
                $scheduleFilters = $this->buildForClassroom($filters, $typeKey, $typeLabel);
                break;

            case OwnerType::LESSON->value:
                $scheduleFilters = $this->buildForLesson($filters, $typeKey, $typeLabel);
                break;

            default:
                throw new Exception("owner_type belirtilmemiş veya geçersiz: " . ($filters["owner_type"] ?? 'null'));
        }

        return $scheduleFilters;
    }


    /**
     * Program türüne göre kısa etiket üretir.
     */
    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            ExamType::MIDTERM->value => 'Ara Sınav Programı',
            ExamType::FINAL->value   => 'Final Programı',
            ExamType::MAKEUP->value  => 'Bütünleme Programı',
            default        => 'Ders Programı',
        };
    }

    // ------------------------------------------------------------------
    // Özel builder metodları
    // ------------------------------------------------------------------

    private function buildForProgram(array $filters, array $semesterNumbers, string $typeKey, string $typeLabel): array
    {
        $result = [];
        $user = AuthMiddleware::user();

        if (!empty($filters["owner_id"])) {
            /** @var Program|null $program */
            $program = (new ProgramRepository())->find($filters['owner_id']);
            $programs = ($program && $program->active) ? [$program] : [];
            if (!empty($filters['semester_no'])) {
                $fileTitle = !empty($programs) ? $programs[0]->name . ' ' . getClassFromSemesterNo((int)$filters['semester_no']) . ' ' . $typeLabel : "Program " . $typeLabel;
            } else {
                $fileTitle = !empty($programs) ? $programs[0]->name . ' ' . $typeLabel : "Program " . $typeLabel;
            }
        } else {
            if ($user && $user->role !== 'admin') {
                $programs = (new ProgramRepository())->getAuthorized('view', ['active' => true]);
            } else {
                $programs = (new ProgramRepository())->findBy(['active' => true]);
            }
            $fileTitle = "Tüm Programlar " . $typeLabel;
        }

        foreach ($programs as $program) {
            foreach ($semesterNumbers as $semester_no) {
                $result[] = [
                    'file_title' => $fileTitle,
                    'title'      => $program->name . " " . getClassFromSemesterNo($semester_no) . " " . $typeLabel,
                    'type'       => OwnerType::PROGRAM->value,
                    'filter'     => $this->baseFilter($filters, $typeKey, OwnerType::PROGRAM->value, $program->id, $semester_no),
                ];
            }
        }

        return $result;
    }

    private function buildForDepartment(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];
        $user = AuthMiddleware::user();
        $semesterNumbers = !empty($filters["semester_no"]) ? [(int)$filters["semester_no"]] : getSemesterNumbers($filters["semester"]);

        if (!empty($filters["owner_id"])) {
            /** @var Department|null $department */
            $department = (new DepartmentRepository())->find($filters['owner_id']);
            $fileTitle  = $department ? $department->name . ' ' . $typeLabel : "Bölüm " . $typeLabel;
            if ($user && $user->role !== 'admin') {
                $programs = (new ProgramRepository())->getAuthorized('view', ['department_id' => $filters['owner_id'], 'active' => true]);
            } else {
                $programs = (new ProgramRepository())->findBy(['department_id' => $filters['owner_id'], 'active' => true]);
            }
            
            foreach ($programs as $program) {
                $subFilters = $this->buildForProgram(
                    array_merge($filters, ['owner_type' => OwnerType::PROGRAM->value, 'owner_id' => $program->id]),
                    $semesterNumbers,
                    $typeKey,
                    $typeLabel
                );
                foreach ($subFilters as &$item) {
                    $item['file_title'] = $fileTitle;
                }
                $result = array_merge($result, $subFilters);
            }
        } else {
            if ($user && $user->role !== 'admin') {
                $departments = (new DepartmentRepository())->getAuthorized('view', ['active' => true]);
            } else {
                $departments = (new DepartmentRepository())->findBy(['active' => true]);
            }
            $fileTitle   = "Tüm Bölümler " . $typeLabel;

            foreach ($departments as $department) {
                $subFilters = $this->buildForDepartment(
                    array_merge($filters, ['owner_type' => 'department', 'owner_id' => $department->id]),
                    $typeKey,
                    $typeLabel
                );
                foreach ($subFilters as &$item) {
                    $item['file_title'] = $fileTitle;
                }
                $result = array_merge($result, $subFilters);
            }
        }

        return $result;
    }

    private function buildForUnit(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];
        $user = AuthMiddleware::user();

        if (!empty($filters["owner_id"])) {
            /** @var Unit|null $unit */
            $unit        = (new UnitRepository())->find($filters['owner_id']);
            $fileTitle   = $unit ? $unit->name . ' ' . $typeLabel : "Birim " . $typeLabel;
            if ($user && $user->role !== 'admin') {
                $departments = (new DepartmentRepository())->getAuthorized('view', ['unit_id' => $filters['owner_id'], 'active' => true]);
            } else {
                $departments = (new DepartmentRepository())->findBy(['unit_id' => $filters['owner_id'], 'active' => true]);
            }

            foreach ($departments as $department) {
                $subFilters = $this->buildForDepartment(
                    array_merge($filters, ['owner_type' => 'department', 'owner_id' => $department->id]),
                    $typeKey,
                    $typeLabel
                );
                foreach ($subFilters as &$item) {
                    $item['file_title'] = $fileTitle;
                }
                $result = array_merge($result, $subFilters);
            }
        } else {
            if ($user && $user->role !== 'admin') {
                $units     = (new UnitRepository())->getAuthorized('view', ['active' => true]);
            } else {
                $units     = (new UnitRepository())->findBy(['active' => true]);
            }
            $fileTitle = "Tüm Birimler " . $typeLabel;

            foreach ($units as $unit) {
                $subFilters = $this->buildForUnit(
                    array_merge($filters, ['owner_type' => 'unit', 'owner_id' => $unit->id]),
                    $typeKey,
                    $typeLabel
                );
                foreach ($subFilters as &$item) {
                    $item['file_title'] = $fileTitle;
                }
                $result = array_merge($result, $subFilters);
            }
        }

        return $result;
    }

    private function buildForUser(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];
        $user = AuthMiddleware::user();

        if (!empty($filters["owner_id"])) {
            /** @var User|null $lecturer */
            $lecturer = (new UserRepository())->find($filters['owner_id']);
            $lecturers = ($lecturer && !in_array($lecturer->role, ['admin', 'user'])) ? [$lecturer] : [];
            $fileTitle = !empty($lecturers) ? $lecturers[0]->getFullName() . " " . $typeLabel : "Hoca " . $typeLabel;
        } else {
            if ($user && $user->role !== 'admin') {
                $lecturers = (new UserRepository())->getAuthorized('view', ['!role' => ['in' => ['admin', 'user']]]);
            } else {
                $lecturers = (new UserRepository())->findBy(['!role' => ['in' => ['admin', 'user']]]);
            }
            $fileTitle = "Tüm Hocalar " . $typeLabel;
        }

        foreach ($lecturers as $lecturer) {
            $result[] = [
                'file_title' => $fileTitle,
                'title'      => $lecturer->getFullName() . " " . $typeLabel,
                'type'       => OwnerType::USER->value,
                'filter'     => $this->baseFilter($filters, $typeKey, OwnerType::USER->value, $lecturer->id, null),
            ];
        }

        return $result;
    }

    private function buildForClassroom(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];
        $user = AuthMiddleware::user();

        if (!empty($filters["owner_id"])) {
            /** @var Classroom|null $classroom */
            $classroom = (new ClassroomRepository())->find($filters['owner_id']);
            $classrooms = $classroom ? [$classroom] : [];
            $fileTitle  = !empty($classrooms) ? $classrooms[0]->name . " " . $typeLabel : "Derslik " . $typeLabel;
        } else {
            if ($user && $user->role !== 'admin') {
                $classrooms = (new ClassroomRepository())->getAuthorized('view');
            } else {
                $classrooms = (new ClassroomRepository())->findBy([]);
            }
            $fileTitle  = "Tüm Derslikler " . $typeLabel;
        }

        foreach ($classrooms as $classroom) {
            $result[] = [
                'file_title' => $fileTitle,
                'title'      => $classroom->name . " " . $typeLabel,
                'type'       => OwnerType::CLASSROOM->value,
                'filter'     => $this->baseFilter($filters, $typeKey, OwnerType::CLASSROOM->value, $classroom->id, null),
            ];
        }

        return $result;
    }

    private function buildForBuilding(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];
        $user = AuthMiddleware::user();

        if (!empty($filters["owner_id"])) {
            /** @var Building|null $building */
            $building = (new BuildingRepository())->find($filters['owner_id']);
            $fileTitle  = $building ? $building->name . ' Derslikleri ' . $typeLabel : "Bina " . $typeLabel;
            if ($user && $user->role !== 'admin') {
                $classrooms = (new ClassroomRepository())->getAuthorized('view', ['building_id' => $filters['owner_id']]);
            } else {
                $classrooms = (new ClassroomRepository())->findBy(['building_id' => $filters['owner_id']]);
            }
        } else {
            $fileTitle  = "Tüm Binaların Derslikleri " . $typeLabel;
            if ($user && $user->role !== 'admin') {
                $classrooms = (new ClassroomRepository())->getAuthorized('view');
            } else {
                $classrooms = (new ClassroomRepository())->findBy([]);
            }
        }

        foreach ($classrooms as $classroom) {
            $result[] = [
                'file_title' => $fileTitle,
                'title'      => $classroom->name . " " . $typeLabel,
                'type'       => OwnerType::CLASSROOM->value,
                'filter'     => $this->baseFilter($filters, $typeKey, OwnerType::CLASSROOM->value, $classroom->id, null),
            ];
        }

        return $result;
    }

    private function buildForClassroomUnit(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];
        $user = AuthMiddleware::user();

        if (!empty($filters["owner_id"])) {
            /** @var Unit|null $unit */
            $unit        = (new UnitRepository())->find($filters['owner_id']);
            $fileTitle   = $unit ? $unit->name . ' Derslikleri ' . $typeLabel : "Birim Derslikleri " . $typeLabel;
            if ($user && $user->role !== 'admin') {
                $buildings = (new BuildingRepository())->getAuthorized('view', ['unit_id' => $filters['owner_id']]);
            } else {
                $buildings = (new BuildingRepository())->findBy(['unit_id' => $filters['owner_id']]);
            }
            $buildingIds = array_column($buildings, 'id');

            if (!empty($buildingIds)) {
                if ($user && $user->role !== 'admin') {
                    $classrooms = (new ClassroomRepository())->getAuthorized('view', ['building_id' => ['in' => $buildingIds]]);
                } else {
                    $classrooms = (new ClassroomRepository())->findBy(['building_id' => ['in' => $buildingIds]]);
                }
            } else {
                $classrooms = [];
            }
        } else {
            $fileTitle  = "Tüm Birim Derslikleri " . $typeLabel;
            if ($user && $user->role !== 'admin') {
                $classrooms = (new ClassroomRepository())->getAuthorized('view');
            } else {
                $classrooms = (new ClassroomRepository())->findBy([]);
            }
        }

        foreach ($classrooms as $classroom) {
            $result[] = [
                'file_title' => $fileTitle,
                'title'      => $classroom->name . " " . $typeLabel,
                'type'       => OwnerType::CLASSROOM->value,
                'filter'     => $this->baseFilter($filters, $typeKey, OwnerType::CLASSROOM->value, $classroom->id, null),
            ];
        }

        return $result;
    }


    private function buildForLesson(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];
        $user = AuthMiddleware::user();

        if (!empty($filters["owner_id"])) {
            /** @var Lesson|null $lesson */
            $lesson = (new LessonRepository())->find($filters['owner_id']);
            $lessons = $lesson ? [$lesson] : [];
            $fileTitle = !empty($lessons) ? $lessons[0]->getFullName(true) . " " . $typeLabel : "Ders " . $typeLabel;
        } else {
            if ($user && $user->role !== 'admin') {
                $lessons = (new LessonRepository())->getAuthorized('view');
            } else {
                $lessons = (new LessonRepository())->findBy([]);
            }
            $fileTitle = "Tüm Dersler " . $typeLabel;
        }

        foreach ($lessons as $lesson) {
            $result[] = [
                'file_title' => $fileTitle,
                'title'      => $lesson->getFullName(true) . " " . $typeLabel,
                'type'       => OwnerType::LESSON->value,
                'filter'     => $this->baseFilter($filters, $typeKey, OwnerType::LESSON->value, $lesson->id, null),
            ];
        }

        return $result;
    }

    /**
     * Her filtre için ortak Schedule sorgulama dizisini oluşturur.
     */
    private function baseFilter(array $filters, string $typeKey, string $ownerType, int $ownerId, ?int $semesterNo): array
    {
        $filter = [
            'type'         => $typeKey,
            'owner_type'   => $ownerType,
            'owner_id'     => $ownerId,
            'semester'     => $filters["semester"],
            'academic_year' => $filters["academic_year"],
        ];

        if ($semesterNo !== null) {
            $filter['semester_no'] = $semesterNo;
        }

        return $filter;
    }

}

