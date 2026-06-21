<?php

namespace App\Services\Export;

use App\Helpers\FilterValidator;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\User;
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
class ScheduleFilterBuilder
{
    /**
     * @param array $filters Doğrulanmış filtre dizisi (FilterValidator'dan geçmiş)
     * @return array Filtre listesi
     * @throws Exception
     */
    public function build(array $filters): array
    {
        $filters         = (new FilterValidator())->validate($filters, "generateScheduleFilters");
        $scheduleFilters = [];
        $semesterNumbers = getSemesterNumbers($filters["semester"]);
        $typeKey         = $filters["type"];
        $typeLabel       = $this->getTypeLabel($typeKey);

        switch ($filters["owner_type"]) {
            case "program":
                $scheduleFilters = $this->buildForProgram($filters, $semesterNumbers, $typeKey, $typeLabel);
                break;

            case "department":
                $scheduleFilters = $this->buildForDepartment($filters, $typeKey);
                break;

            case "user":
                $scheduleFilters = $this->buildForUser($filters, $typeKey, $typeLabel);
                break;

            case "classroom":
                $scheduleFilters = $this->buildForClassroom($filters, $typeKey, $typeLabel);
                break;

            case "lesson":
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
            'midterm-exam' => 'Ara Sınav Programı',
            'final-exam'   => 'Final Programı',
            'makeup-exam'  => 'Bütünleme Programı',
            default        => 'Ders Programı',
        };
    }

    // ------------------------------------------------------------------
    // Özel builder metodları
    // ------------------------------------------------------------------

    private function buildForProgram(array $filters, array $semesterNumbers, string $typeKey, string $typeLabel): array
    {
        $result = [];

        if (!empty($filters["owner_id"])) {
            $program = (new Program())->find($filters["owner_id"]);
            foreach ($semesterNumbers as $semester_no) {
                $result[] = [
                    'file_title' => $program->name . ' ' . $typeLabel,
                    'title'      => $program->name . " " . getClassFromSemesterNo($semester_no) . " " . $typeLabel,
                    'type'       => 'program',
                    'filter'     => $this->baseFilter($filters, $typeKey, 'program', $program->id, $semester_no),
                ];
            }
        } else {
            $programs = (new Program())->get()->where(['active' => true])->all();
            foreach ($programs as $program) {
                foreach ($semesterNumbers as $semester_no) {
                    $result[] = [
                        'file_title' => "Tüm Programlar " . $typeLabel,
                        'title'      => $program->name . " " . getClassFromSemesterNo($semester_no) . " " . $typeLabel,
                        'type'       => 'program',
                        'filter'     => $this->baseFilter($filters, $typeKey, 'program', $program->id, $semester_no),
                    ];
                }
            }
        }

        return $result;
    }

    private function buildForDepartment(array $filters, string $typeKey): array
    {
        $result = [];

        if (!empty($filters["owner_id"])) {
            $programs = (new Program())->get()->where(['department_id' => $filters['owner_id']])->all();
        } else {
            $programs = (new Program())->get()->where(['active' => true])->all();
        }

        foreach ($programs as $program) {
            $programFilters = array_merge($filters, ['owner_type' => 'program', 'owner_id' => $program->id]);
            $result = array_merge($result, $this->build($programFilters));
        }

        return $result;
    }

    private function buildForUser(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];

        if (!empty($filters["owner_id"])) {
            $lecturer = (new User())->find($filters["owner_id"]);
            $result[] = [
                'file_title' => $lecturer->getFullName(true) . " " . $typeLabel,
                'title'      => $lecturer->getFullName() . " " . $typeLabel,
                'type'       => 'user',
                'filter'     => $this->baseFilter($filters, $typeKey, 'user', $lecturer->id, null),
            ];
        } else {
            $lecturers = (new User())->get()->where(['!role' => 'user'])->all();
            foreach ($lecturers as $lecturer) {
                $result[] = [
                    'file_title' => "Tüm Hocalar " . $typeLabel,
                    'title'      => $lecturer->getFullName() . " " . $typeLabel,
                    'type'       => 'user',
                    'filter'     => $this->baseFilter($filters, $typeKey, 'user', $lecturer->id, null),
                ];
            }
        }

        return $result;
    }

    private function buildForClassroom(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];

        if (!empty($filters["owner_id"])) {
            $classroom = (new Classroom())->find($filters["owner_id"]);
            $result[]  = [
                'file_title' => $classroom->name . " " . $typeLabel,
                'title'      => $classroom->name . " " . $typeLabel,
                'type'       => 'classroom',
                'filter'     => $this->baseFilter($filters, $typeKey, 'classroom', $classroom->id, null),
            ];
        } else {
            $classrooms = (new Classroom())->get()->all();
            foreach ($classrooms as $classroom) {
                $result[] = [
                    'file_title' => "Tüm Derslikler " . $typeLabel,
                    'title'      => $classroom->name . " " . $typeLabel,
                    'type'       => 'classroom',
                    'filter'     => $this->baseFilter($filters, $typeKey, 'classroom', $classroom->id, null),
                ];
            }
        }

        return $result;
    }

    private function buildForLesson(array $filters, string $typeKey, string $typeLabel): array
    {
        $result = [];

        if (!empty($filters["owner_id"])) {
            $lesson   = (new Lesson())->find($filters["owner_id"]);
            $result[] = [
                'file_title' => $lesson->getFullName(true) . " " . $typeLabel,
                'title'      => $lesson->getFullName(true) . " " . $typeLabel,
                'type'       => 'lesson',
                'filter'     => $this->baseFilter($filters, $typeKey, 'lesson', $lesson->id, null),
            ];
        } else {
            $lessons = (new Lesson())->get()->all();
            foreach ($lessons as $lesson) {
                $result[] = [
                    'file_title' => "Tüm Dersler " . $typeLabel,
                    'title'      => $lesson->getFullName(true) . " " . $typeLabel,
                    'type'       => 'lesson',
                    'filter'     => $this->baseFilter($filters, $typeKey, 'lesson', $lesson->id, null),
                ];
            }
        }

        return $result;
    }

    /**
     * Her filtre için ortak Schedule sorgulama dizisini oluşturur.
     */
    private function baseFilter(array $filters, string $typeKey, string $ownerType, int $ownerId, ?int $semesterNo): array
    {
        return [
            'type'         => $typeKey,
            'owner_type'   => $ownerType,
            'owner_id'     => $ownerId,
            'semester_no'  => $semesterNo,
            'semester'     => $filters["semester"],
            'academic_year' => $filters["academic_year"],
        ];
    }
}
