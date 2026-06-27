<?php

namespace App\Services\Import;

use App\Controllers\ClassroomController;
use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Repositories\UserRepository;
use App\Core\Database;
use App\Core\Log;
use App\Models\Lesson;
use App\Services\LessonService;
use App\Enums\ClassroomType;
use Exception;
use Monolog\Logger;
use App\Repositories\DepartmentRepository;
use App\Repositories\ProgramRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use function App\Helpers\formatLessonName;

/**
 * Excel dosyasından dersleri içe aktarır.
 */
class LessonImporter
{
    private Spreadsheet $spreadsheet;
    private array $formData;
    private array $cache = [
        'departments'   => [],
        'programs'      => [],
        'users_by_name' => [],
    ];

    public function __construct(Spreadsheet $spreadsheet, array $formData = [])
    {
        $this->spreadsheet = $spreadsheet;
        $this->formData    = $formData;
    }

    protected function logger(): Logger
    {
        return Log::logger();
    }

    protected function logContext(array $extra = []): array
    {
        return Log::context($this, $extra);
    }

    /**
     * @return array{status: string, added: int, updated: int, errorCount: int, errors: array, addedLessons: array, updatedLessons: array}
     * @throws Exception
     */
    public function import(): array
    {
        $userRepository       = new UserRepository();
        $departmentController = new DepartmentController();
        $programController    = new ProgramController();
        $lessonsController    = new LessonController();
        $lessonService        = new LessonService();

        $errorCount    = 0;
        $errors        = [];
        $addedLessons  = [];
        $updatedLessons = [];

        $sheet = $this->spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray();

        // Başlık satırını al ve doğrula
        $headers = array_shift($rows);
        $headers = array_map(fn($item) => is_string($item) ? trim($item) : $item, $headers);
        $headers = array_values(array_filter($headers, fn($item) => !is_null($item) && $item !== ''));
        $expectedHeaders = ["Bölüm", "Program", "Yarıyılı", "Türü", "Dersin Kodu", 'Grup No', "Dersin Adı", "Saati", "Mevcudu", "Hocası", "Derslik türü"];

        if ($headers !== $expectedHeaders) {
            throw new Exception("Excel başlıkları beklenen formatta değil!");
        }
        if (!isset($this->formData['academic_year']) || !isset($this->formData['semester'])) {
            throw new Exception("Yıl veya dönem belirtilmemiş");
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            foreach ($rows as $rowIndex => $row) {
                // Boş satır kontrolü
                $isEmpty = true;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string) $cell) !== '') {
                        $isEmpty = false;
                        break;
                    }
                }
                if ($isEmpty) continue;

                $hasError = false;
                [$department_name, $program_name, $semester_no, $type, $code, $group_no, $name, $hours, $size, $lecturer_full_name, $classroom_type] = array_map(
                    fn($item) => trim((string) ($item ?? '')),
                    $row
                );

                $data      = [$department_name, $program_name, $semester_no, $type, $code, $group_no, $name, $hours, $size, $lecturer_full_name, $classroom_type];
                $rowErrors = [];

                foreach ($data as $dataIndex => $value) {
                    if ($value === null || $value === "") {
                        $rowErrors[] = $expectedHeaders[$dataIndex] . ". sütunda eksik veri!";
                        $hasError    = true;
                    }
                }

                // Caching
                if (!isset($this->cache['departments'][$department_name])) {
                    $this->cache['departments'][$department_name] = (new DepartmentRepository())->findByName($department_name) ?: false;
                }
                $department = $this->cache['departments'][$department_name];

                $programCacheKey = $department_name . '_' . $program_name;
                if (!isset($this->cache['programs'][$programCacheKey])) {
                    $this->cache['programs'][$programCacheKey] = (new ProgramRepository())->findByName($program_name) ?: false;
                }
                $program = $this->cache['programs'][$programCacheKey];

                if (!isset($this->cache['users_by_name'][$lecturer_full_name])) {
                    $this->cache['users_by_name'][$lecturer_full_name] = $userRepository->findByFullName($lecturer_full_name);
                }
                $lecturer = $this->cache['users_by_name'][$lecturer_full_name];

                if (!$lecturer) { $rowErrors[] = "Hoca hatalı! ({$lecturer_full_name})"; $hasError = true; }
                if (!$program)  { $rowErrors[] = "Program hatalı! ({$program_name})"; $hasError = true; }
                if (!$department) { $rowErrors[] = "Bölüm hatalı! ({$department_name})"; $hasError = true; }

                if ($hasError) {
                    $errors[] = "Satır " . ($rowIndex + 2) . ": " . implode(" | ", $rowErrors);
                    $errorCount++;
                    continue;
                }

                $lessonData = [
                    'code'           => strtoupper($code),
                    'group_no'       => $group_no,
                    'name'           => formatLessonName($name),
                    'size'           => $size,
                    'hours'          => $hours,
                    'type'           => array_search(trim($type), (new LessonController())->getTypeList()),
                    'semester_no'    => $semester_no,
                    'lecturer_id'    => $lecturer->id,
                    'department_id'  => $department->id,
                    'program_id'     => $program->id,
                    'semester'       => $this->formData['semester'],
                    'classroom_type' => array_search(trim($classroom_type), ClassroomType::toArray()),
                    'academic_year'  => $this->formData['academic_year'],
                ];

                $lesson = (new Lesson())->get()->where(['code' => $code, 'program_id' => $program->id, 'group_no' => $group_no])->first();
                if ($lesson) {
                    $lesson->fill($lessonData);
                    $lessonService->updateLesson($lesson);
                    $updatedLessons[$lesson->id] = $lesson->getFullName(true);
                } else {
                    $lesson = new Lesson();
                    $lesson->fill($lessonData);
                    $lessonsController->saveNew($lesson);
                    $addedLessons[$lesson->id] = $lesson->getFullName(true);
                }
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return [
            "status"         => "success",
            "added"          => count($addedLessons),
            "updated"        => count($updatedLessons),
            "errorCount"     => $errorCount,
            "errors"         => $errors,
            "addedLessons"   => $addedLessons,
            "updatedLessons" => $updatedLessons,
        ];
    }
}
