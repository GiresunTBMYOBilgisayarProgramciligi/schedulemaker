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
use App\Enums\LessonType;
use App\Validators\LessonValidator;
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

        Database::transaction(function () use ($rows, $expectedHeaders, &$errorCount, &$errors, &$addedLessons, &$updatedLessons, $userRepository, $lessonService) {
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

                // Caching Department
                $department = null;
                if (!empty($department_name)) {
                    if (!isset($this->cache['departments'][$department_name])) {
                        $this->cache['departments'][$department_name] = (new DepartmentRepository())->findByName($department_name) ?: false;
                    }
                    $department = $this->cache['departments'][$department_name];
                }

                // Caching Program
                $program = null;
                if (!empty($program_name)) {
                    $programCacheKey = $program_name;
                    if (!isset($this->cache['programs'][$programCacheKey])) {
                        $this->cache['programs'][$programCacheKey] = (new ProgramRepository())->findByName($program_name) ?: false;
                    }
                    $program = $this->cache['programs'][$programCacheKey];
                }

                if (!empty($department_name) && $department === false) {
                    $rowErrors[] = "Bölüm bulunamadı! ({$department_name})";
                }
                if (!empty($program_name) && $program === false) {
                    $rowErrors[] = "Program bulunamadı! ({$program_name})";
                }

                // Caching Lecturer
                $lecturer = null;
                if (!empty($lecturer_full_name)) {
                    if (!isset($this->cache['users_by_name'][$lecturer_full_name])) {
                        $this->cache['users_by_name'][$lecturer_full_name] = clone $userRepository->findByFullName($lecturer_full_name) ?: false;
                    }
                    $lecturer = $this->cache['users_by_name'][$lecturer_full_name];
                }

                if (!empty($lecturer_full_name) && $lecturer === false) {
                    $rowErrors[] = "Hoca bulunamadı! ({$lecturer_full_name})";
                } elseif (empty($lecturer_full_name)) {
                    $rowErrors[] = "Hoca belirtilmelidir!";
                }

                // Uyumluluk Kontrolü (Bölüm - Program)
                if ($department && $program && $program->department_id !== $department->id) {
                    $rowErrors[] = "Uyumsuzluk: {$department->name} bölümünün {$program->name} programı yok!";
                    $hasError = true;
                } elseif (empty($department_name) && !empty($program_name)) {
                    $rowErrors[] = "Uyumsuzluk: Program belirtildiğinde bölüm de belirtilmelidir!";
                    $hasError = true;
                }
                // Veriyi hazırlama - validation öncesi (raw string/mapped)
                $lessonTypeEnum = LessonType::fromLabel(trim($type));
                $clsTypeEnum = ClassroomType::fromLabel(trim($classroom_type));

                $lessonData = [
                    'code'           => strtoupper($code),
                    'group_no'       => $group_no,
                    'name'           => $name,
                    'size'           => $size,
                    'hours'          => $hours,
                    'type'           => $lessonTypeEnum?->value ?? $type, // Geçersizse ham veriyi bırak, validator yakalasın
                    'semester_no'    => $semester_no,
                    'lecturer_id'    => $lecturer ? $lecturer->id : null,
                    'department_id'  => $department ? $department->id : null,
                    'program_id'     => $program ? $program->id : null,
                    'semester'       => $this->formData['semester'],
                    'classroom_type' => $clsTypeEnum?->value ?? $classroom_type,
                    'academic_year'  => $this->formData['academic_year'],
                ];

                $lessonDTO = null;
                try {
                    $lessonValidator = new LessonValidator();
                    $lessonDTO = $lessonValidator->getDTO($lessonData);
                } catch (\App\Exceptions\ValidationException $e) {
                    foreach ($e->getValidationErrors() as $field => $msg) {
                        $rowErrors[] = $msg;
                    }
                }

                if (!empty($rowErrors)) {
                    $errors[] = "Satır " . ($rowIndex + 2) . ": " . implode(" | ", $rowErrors);
                    $errorCount++;
                    continue;
                }

                $lesson = (new Lesson())->get()->where(['code' => $code, 'program_id' => $program->id, 'group_no' => $group_no])->first();
                if ($lesson) {
                    $lesson->fill($lessonDTO->toArray());
                    $lessonService->updateLesson($lesson);
                    $updatedLessons[$lesson->id] = $lesson->getFullName(true);
                } else {
                    $lessonId = $lessonService->saveNew($lessonDTO);
                    $addedLessons[$lessonId] = $lessonDTO->name;
                }
            }

        });

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
