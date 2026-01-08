<?php

namespace App\Core;

use App\Controllers\ClassroomController;
use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Controllers\ScheduleController;
use App\Controllers\UserController;
use App\Helpers\FilterValidator;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\User;
use Exception;
use Monolog\Logger;
use JetBrains\PhpStorm\NoReturn;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSettingValue;

class ImportExportManager
{
    private Spreadsheet $importFile;
    private Spreadsheet $exportFile;

    private $sheet;
    private array $formData = [];
    private array $cache = [
        'departments' => [],
        'programs' => [],
        'users_by_mail' => [],
        'users_by_name' => []
    ];

    public function __construct(?array $uploadedFile = null, array $formData = [])
    {
        if (!is_null($uploadedFile)) {
            $this->prepareImportFile($uploadedFile['file']);
            $this->formData = $formData;
        } else {
            $this->prepareExportFile();
        }

    }
    /**
     * Shared application logger for all controllers.
     */
    protected function logger(): Logger
    {
        return Log::logger();
    }

    /**
     * Standard logging context used across controllers.
     * Adds current user, caller method, URL and IP.
     */
    protected function logContext(array $extra = []): array
    {
        return Log::context($this, $extra);
    }

    private function prepareExportFile(): void
    {
        $this->exportFile = new Spreadsheet();
        $this->sheet = $this->exportFile->getActiveSheet();
    }

    public function prepareImportFile(array $uploadedFile): void
    {
        $this->importFile = IOFactory::load($uploadedFile['tmp_name']);
        $this->sheet = $this->importFile->getActiveSheet();
    }

    /**
     * Excel dosyasından kullanıcıları içe aktarır.
     * @return array
     * @throws Exception
     */
    public function importUsersFromExcel(): array
    {
        $userController = new UserController();
        $departmentController = new DepartmentController();
        $programController = new ProgramController();
        $addedCount = 0;
        $updatedCount = 0;
        $errorCount = 0;
        $errors = [];


        $rows = $this->sheet->toArray();
        // Başlık satırını al ve doğrula
        $headers = array_shift($rows);
        $expectedHeaders = ["Mail", "Ünvanı", "Adı", "Soyadı", "Görevi", "Bölümü", "Programı"];
        $headers = array_map(fn($item) => is_string($item) ? trim($item) : $item, $headers);
        $headers = array_values(array_filter($headers, fn($item) => !is_null($item) && $item !== ''));

        if ($headers !== $expectedHeaders) {
            throw new Exception("Excel başlıkları beklenen formatta değil!");
        }
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                // Boş satır kontrolü
                $is_empty = true;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string) $cell) !== '') {
                        $is_empty = false;
                        break;
                    }
                }
                if ($is_empty)
                    continue;

                [$mail, $title, $name, $last_name, $role, $department_name, $program_name] = array_map(function ($item) {
                    return trim((string) ($item ?? ''));
                }, $row);

                if (empty($mail) or empty($title) or empty($name) or empty($last_name) or empty($role)) {
                    $errors[] = "Satır " . ($index + 2) . ": Eksik veri!";
                    $errorCount++;
                    continue;
                }

                // Caching for Department
                if (!isset($this->cache['departments'][$department_name])) {
                    $this->cache['departments'][$department_name] = $departmentController->getDepartmentByName($department_name);
                }
                $department = $this->cache['departments'][$department_name];

                // Caching for Program
                $program_cache_key = $department_name . '_' . $program_name;
                if (!isset($this->cache['programs'][$program_cache_key])) {
                    $this->cache['programs'][$program_cache_key] = $programController->getProgramByName($program_name);
                }
                $program = $this->cache['programs'][$program_cache_key];

                if (!$department || !$program) {
                    $rowErrors = [];
                    if (!$department)
                        $rowErrors[] = "Bölüm bulunamadı! (" . $department_name . ")";
                    if (!$program)
                        $rowErrors[] = "Program bulunamadı! (" . $program_name . ")";
                    $errors[] = "Satır " . ($index + 2) . ": " . implode(" | ", $rowErrors);
                    $errorCount++;
                    continue;
                }

                $userData = [
                    'mail' => $mail,
                    'title' => $title,
                    'name' => mb_convert_case($name, MB_CASE_TITLE, "UTF-8"),
                    'last_name' => mb_strtoupper($last_name, "UTF-8"),
                    'role' => array_search($role, $userController->getRoleList()),
                    'department_id' => $department->id ?? null,
                    'program_id' => $program->id ?? null,
                ];

                // Check cache/db for user
                if (!isset($this->cache['users_by_mail'][$mail])) {
                    $this->cache['users_by_mail'][$mail] = $userController->getUserByEmail($mail);
                }
                $user = $this->cache['users_by_mail'][$mail];

                if ($user) {
                    $user->fill($userData);
                    $user->password = null;
                    $userController->updateUser($user);
                    $updatedCount++;
                } else {
                    $userController->saveNew($userData);
                    $addedCount++;
                    // Yeni eklenen kullanıcıyı da cache'e alalım (id'si oluştu)
                    $this->cache['users_by_mail'][$mail] = $userController->getUserByEmail($mail);
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        return [
            "status" => "success",
            "added" => $addedCount,
            "updated" => $updatedCount,
            "errorCount" => $errorCount,
            "errors" => $errors
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function importLessonsFromExcel(): array
    {
        $userController = new UserController();
        $departmentController = new DepartmentController();
        $programController = new ProgramController();
        $lessonsController = new LessonController();
        $errorCount = 0;
        $errors = [];
        $addedLessons = [];
        $updatedLessons = [];
        // Excel dosyasını aç

        $this->sheet = $this->importFile->getActiveSheet();
        $rows = $this->sheet->toArray();
        // Başlık satırını al ve doğrula
        $headers = array_shift($rows);
        $headers = array_map(fn($item) => is_string($item) ? trim($item) : $item, $headers);
        $headers = array_values(array_filter($headers, fn($item) => !is_null($item) && $item !== ''));
        $expectedHeaders =
            ["Bölüm", "Program", "Yarıyılı", "Türü", "Dersin Kodu", 'Grup No', "Dersin Adı", "Saati", "Mevcudu", "Hocası", "Derslik türü"];


        if ($headers !== $expectedHeaders) {
            throw new Exception("Excel başlıkları beklenen formatta değil!");
        }
        if (!isset($this->formData['academic_year']) or !isset($this->formData['semester'])) {
            throw new Exception("Yıl veya dönem belirtilmemiş");
        }
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            foreach ($rows as $rowIndex => $row) {
                // Boş satır kontrolü
                $is_empty = true;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string) $cell) !== '') {
                        $is_empty = false;
                        break;
                    }
                }
                if ($is_empty)
                    continue;

                $hasError = false;// her bir satıra hatasız başlanıyor
                [$department_name, $program_name, $semester_no, $type, $code, $group_no, $name, $hours, $size, $lecturer_full_name, $classroom_type] = array_map(function ($item) {
                    return trim((string) ($item ?? ''));
                }, $row);

                $data = [$department_name, $program_name, $semester_no, $type, $code, $group_no, $name, $hours, $size, $lecturer_full_name, $classroom_type];
                $rowErrors = []; // Satır bazlı hata toplayıcı
                // Her bir değeri kontrol et
                foreach ($data as $dataIndex => $value) {
                    if ($value === null || $value === "") {
                        $rowErrors[] = $expectedHeaders[$dataIndex] . ". sütunda eksik veri!";
                        $hasError = true; // Bu satırda hata olduğunu belirt
                    }
                }

                // Caching for Department
                if (!isset($this->cache['departments'][$department_name])) {
                    $this->cache['departments'][$department_name] = $departmentController->getDepartmentByName($department_name);
                }
                $department = $this->cache['departments'][$department_name];

                // Caching for Program
                $program_cache_key = $department_name . '_' . $program_name;
                if (!isset($this->cache['programs'][$program_cache_key])) {
                    $this->cache['programs'][$program_cache_key] = $programController->getProgramByName($program_name);
                }
                $program = $this->cache['programs'][$program_cache_key];

                // Caching for Lecturer
                if (!isset($this->cache['users_by_name'][$lecturer_full_name])) {
                    $this->cache['users_by_name'][$lecturer_full_name] = $userController->getUserByFullName($lecturer_full_name);
                }
                $lecturer = $this->cache['users_by_name'][$lecturer_full_name];

                if (!$lecturer) {
                    $rowErrors[] = "Hoca hatalı! (" . $lecturer_full_name . ")";
                    $hasError = true;
                }
                if (!$program) {
                    $rowErrors[] = "Program hatalı! (" . $program_name . ")";
                    $hasError = true;
                }
                if (!$department) {
                    $rowErrors[] = "Bölüm hatalı! (" . $department_name . ")";
                    $hasError = true;
                }

                // Eğer bu satırda hata varsa, bir sonraki satıra geç
                if ($hasError) {
                    $errors[] = "Satır " . ($rowIndex + 2) . ": " . implode(" | ", $rowErrors);
                    $errorCount++;
                    continue;
                }
                $lessonData = [
                    'code' => $code,
                    'group_no' => $group_no,
                    'name' => $name,
                    'size' => $size,
                    'hours' => $hours,
                    'type' => array_search(trim($type), (new LessonController())->getTypeList()),
                    'semester_no' => $semester_no,
                    'lecturer_id' => $lecturer->id,
                    'department_id' => $department->id,
                    'program_id' => $program->id,
                    'semester' => $this->formData['semester'],
                    'classroom_type' => array_search(trim($classroom_type), (new ClassroomController())->getTypeList()),
                    'academic_year' => $this->formData['academic_year'],
                ];
                //Ders ders kodu, program_id ve group_no göre benzersiz kaydediliyor. Aynı ders koduna sahip dersler var
                $lesson = (new Lesson())->get()->where(['code' => $code, 'program_id' => $program->id, 'group_no' => $group_no])->first();
                if ($lesson) {
                    $lesson->fill($lessonData);
                    $lessonsController->updateLesson($lesson);
                    $updatedLessons[] = $lesson->getFullName();
                } else {
                    $lesson = new Lesson();
                    $lesson->fill($lessonData);
                    $lessonsController->saveNew($lesson);
                    $addedLessons[] = $lesson->getFullName();
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        return [
            "status" => "success",
            "added" => count($addedLessons),
            "updated" => count($updatedLessons),
            "errorCount" => $errorCount,
            "errors" => $errors,
            "addedLessons" => $addedLessons,
            "updatedLessons" => $updatedLessons,
        ];
    }

    /**
     * @throws Exception
     * todo bu metodun çıktısı başlık=> filtre şeklinde bunu dizi olarak verip diziye başlık, tür (ders, hoca, derslik) şekilde kullanmak daha uygun olur. Bu sayede excel çıktıları türkerine göre daha kolay düzenlenir
     */
    private function generateScheduleFilters($filters): array
    {
        $filters = (new FilterValidator())->validate($filters, "generateScheduleFilters");
        $scheduleFilters = [];
        $semesterNumbers = getSemesterNumbers($filters["semester"]);

        switch ($filters["owner_type"]) {
            case "program":
                if (isset($filters["owner_id"]) && key_exists("owner_id", $filters)) {
                    // Eğer filtrelerde owner_id değeri tanımlanmışsa o programa ait ders programları için
                    // oluşturulan filtre $scheduleFilters dizisine eklenir
                    foreach ($semesterNumbers as $semester_no) {
                        // id numarası belirtilen program Modeli oluşturulur
                        $program = (new Program())->find($filters["owner_id"]); // Burada program_id yerine owner_id kullanılmalı
                        // anahtarı program adı ve yarıyılı olacak şekilde filtrelere eklenir
                        $scheduleFilters[] = [
                            'file_title' => $program->name . 'Ders Programı',
                            'title' => $program->name . " " . getClassFromSemesterNo($semester_no) . " Ders Programı",
                            'type' => 'program',
                            'filter' => [
                                "semester_no" => $semester_no,
                                'owner_type' => 'program',
                                'owner_id' => $program->id,
                                'type' => $filters["type"],
                                'semester' => $filters["semester"],
                                'academic_year' => $filters["academic_year"],
                            ]
                        ];
                    }
                } else {
                    //id belirtilmemişse tüm programlar için filtre oluşturulacak
                    $programs = (new Program())->get()->all();
                    foreach ($programs as $program) {
                        foreach ($semesterNumbers as $semester_no) {
                            $scheduleFilters[] = [
                                'file_title' => "Tüm Programlar Ders Programı",
                                'title' => $program->name . " " . getClassFromSemesterNo($semester_no) . " Ders Programı",
                                'type' => 'program',
                                'filter' => [
                                    "semester_no" => $semester_no,
                                    'owner_type' => 'program',
                                    'owner_id' => $program->id,
                                    'type' => $filters["type"],
                                    'semester' => $filters["semester"],
                                    'academic_year' => $filters["academic_year"],
                                ]
                            ];
                        }
                    }
                }
                break;
            case "department":
                if (isset($filters["owner_id"]) && key_exists("owner_id", $filters)) {
                    //belirtilen bölüme ait programların listesi
                    $lecturers = (new Program())->get()->where(['department_id' => $filters['owner_id']])->all();
                    foreach ($lecturers as $program) {
                        // Tüm filtreleri alt çağrıya aktarıyoruz
                        $programFilters = array_merge($filters, [
                            "owner_type" => "program",
                            "owner_id" => $program->id
                        ]);

                        // Alt çağrıdan gelen filtreleri mevcut filtrelere ekliyoruz (üzerine yazmak yerine)
                        $scheduleFilters = array_merge(
                            $scheduleFilters,
                            $this->generateScheduleFilters($programFilters)
                        );
                    }
                } else {
                    // Tüm filtre parametrelerini alt çağrıya aktarıyoruz
                    $programFilters = array_merge($filters, ["owner_type" => "program"]);
                    $scheduleFilters = $this->generateScheduleFilters($programFilters);
                }
                break;
            case "user":
                if (isset($filters["owner_id"]) && key_exists("owner_id", $filters)) {
                    // Eğer filtrelerde owner_id değeri tanımlanmışsa o hocaya ait ders programları için
                    // oluşturulan filtre $scheduleFilters dizisine eklenir
                    // id numarası belirtilen kullanıcı Modeli oluşturulur
                    $lecturer = (new User())->find($filters["owner_id"]);
                    $scheduleFilters[] = [
                        'file_title' => $lecturer->getFullName() . " Ders Programı",
                        'title' => $lecturer->getFullName() . " Ders Programı",
                        'type' => 'user',
                        'filter' => [
                            "semester_no" => null,
                            'owner_type' => 'user',
                            'owner_id' => $lecturer->id,
                            'type' => $filters["type"],
                            'semester' => $filters["semester"],
                            'academic_year' => $filters["academic_year"],
                        ]
                    ];
                } else {
                    //id belirtilmemişse tüm hocalar için filtre oluşturulacak
                    $lecturers = (new User())->get()->where(['!role' => 'user'])->all();
                    foreach ($lecturers as $lecturer) {
                        $scheduleFilters[] = [
                            'file_title' => "Tüm Hocalar Ders Programı",
                            'title' => $lecturer->getFullName() . " Ders Programı",
                            'type' => 'user',
                            'filter' => [
                                "semester_no" => null,
                                'owner_type' => 'user',
                                'owner_id' => $lecturer->id,
                                'type' => $filters["type"],
                                'semester' => $filters["semester"],
                                'academic_year' => $filters["academic_year"],
                            ]
                        ];
                    }
                }
                break;
            case "classroom":
                if (isset($filters["owner_id"]) && key_exists("owner_id", $filters)) {
                    // Eğer filtrelerde owner_id değeri tanımlanmışsa o derliğe ait ders programları için
                    // oluşturulan filtre $scheduleFilters dizisine eklenir
                    // id numarası belirtilen kullanıcı Modeli oluşturulur
                    $classroom = (new Classroom())->find($filters["owner_id"]);
                    $scheduleFilters[] = [
                        'file_title' => $classroom->name . " Ders Programı",
                        'title' => $classroom->name . " Ders Programı",
                        'type' => 'classroom',
                        'filter' => [
                            "semester_no" => null,
                            'owner_type' => 'classroom',
                            'owner_id' => $classroom->id,
                            'type' => $filters["type"],
                            'semester' => $filters["semester"],
                            'academic_year' => $filters["academic_year"],
                        ]
                    ];
                } else {
                    //id belirtilmemişse tüm derslikler için filtre oluşturulacak
                    $classrooms = (new Classroom())->get()->all();
                    foreach ($classrooms as $classroom) {
                        $scheduleFilters[] = [
                            'file_title' => "Tüm Derslikler Ders Programı",
                            'title' => $classroom->name . " Ders Programı",
                            'type' => 'classroom',
                            'filter' => [
                                "semester_no" => null,
                                'owner_type' => 'classroom',
                                'owner_id' => $classroom->id,
                                'type' => $filters["type"],
                                'semester' => $filters["semester"],
                                'academic_year' => $filters["academic_year"],
                            ]
                        ];
                    }
                }
                break;
            case "lesson":
                if (isset($filters["owner_id"]) && key_exists("owner_id", $filters)) {
                    // Eğer filtrelerde owner_id değeri tanımlanmışsa o Derse ait ders programları için
                    // oluşturulan filtre $scheduleFilters dizisine eklenir
                    // id numarası belirtilen kullanıcı Modeli oluşturulur
                    $lesson = (new Lesson())->find($filters["owner_id"]);
                    $scheduleFilters[] = [
                        'file_title' => $lesson->getFullName() . " Ders Programı",
                        'title' => $lesson->getFullName() . " Ders Programı",
                        'type' => 'lesson',
                        'filter' => [
                            "semester_no" => null,
                            'owner_type' => 'lesson',
                            'owner_id' => $lesson->id,
                            'type' => $filters["type"],
                            'semester' => $filters["semester"],
                            'academic_year' => $filters["academic_year"],
                        ]
                    ];
                } else {
                    //id belirtilmemişse tüm dersler için filtre oluşturulacak
                    $lessons = (new Lesson())->get()->all();
                    foreach ($lessons as $lesson) {
                        $scheduleFilters[] = [
                            'file_title' => "Tüm Hocalar Ders Programı",
                            'title' => $lesson->getFullName() . " Ders Programı",
                            'type' => 'lesson',
                            'filter' => [
                                "semester_no" => null,
                                'owner_type' => 'lesson',
                                'owner_id' => $lesson->id,
                                'type' => $filters["type"],
                                'semester' => $filters["semester"],
                                'academic_year' => $filters["academic_year"],
                            ]
                        ];
                    }
                }
                break;
            default:
                throw new Exception("owner_type belirtilmemiş");
        }

        return $scheduleFilters;
    }

    /**
     * @throws Exception
     */
    #[NoReturn]
    public function exportSchedule($filters = []): void
    {
        // Önce filtreleri doğrula (AjaxRouter zaten yapmıştı ama garantiye alalım)
        $filters = (new FilterValidator())->validate($filters, "exportScheduleAction");

        // Kullanıcı tercihlerini al (Filtreler doğrulandıktan sonra)
        $showOptions = [
            'show_code' => !isset($filters['show_code']) || (string) $filters['show_code'] === '1',
            'show_lecturer' => !isset($filters['show_lecturer']) || (string) $filters['show_lecturer'] === '1',
            'show_program' => !isset($filters['show_program']) || (string) $filters['show_program'] === '1'
        ];
        $scheduleController = new ScheduleController();

        // Yazı tipi ayarları
        $this->exportFile->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);

        // Hesaplamayı döngü öncesinde yaparak $lastCol değişkenini garantiye alıyoruz
        $type = in_array($filters['type'] ?? '', ['midterm-exam', 'final-exam', 'makeup-exam']) ? 'exam' : 'lesson';
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
        $colsPerDay = (($filters['owner_type'] ?? '') === 'classroom') ? 1 : 2;
        $totalCols = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);

        $row = $this->createFileTitle($filters);

        foreach ($this->generateScheduleFilters($filters) as $scheduleFilter) {
            // Yeni yapıya göre Schedule modelini bul
            $schedule = (new Schedule())->get()
                ->where($scheduleFilter['filter'])
                ->with("items")
                ->first();

            if (!$schedule || empty($schedule->items)) {
                continue;
            }

            // Grid yapısını al
            $weekCount = ($schedule->type === 'final-exam') ? 2 : 1;
            $type = in_array($schedule->type, ['midterm-exam', 'final-exam', 'makeup-exam']) ? 'exam' : 'lesson';
            $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);
            $scheduleRows = $scheduleController->prepareScheduleRows($schedule, 'excel', $maxDayIndex);

            foreach ($scheduleRows as $weekIndex => $slots) {
                // Her hafta için ayrı bir başlık veya boşluk
                $isClassroom = ($scheduleFilter['type'] === 'classroom');
                $colsPerDay = $isClassroom ? 1 : 2;
                $totalCols = ($maxDayIndex + 1) * $colsPerDay + 1;
                $lastCol = Coordinate::stringFromColumnIndex($totalCols);

                if ($weekIndex > 0) {
                    $row += 1;
                    $this->sheet->setCellValue("A{$row}", ($weekIndex + 1) . ". HAFTA");
                    $this->sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                    $this->sheet->getStyle("A{$row}")->getFont()->setBold(true);
                    $row++;
                }

                // Program başlığı (Turuncu bar)
                $this->sheet->setCellValue("A{$row}", $scheduleFilter['title'] . ($weekCount > 1 ? " (" . ($weekIndex + 1) . ". Hafta)" : ""));
                $this->sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $this->sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true)->setSize(11);
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ffbf00');

                $firstCell = "A" . ($row + 1);
                $row++;

                // Gün başlıklarını yaz
                $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
                $this->sheet->setCellValue("A{$row}", "Saat");
                for ($i = 0; $i <= $maxDayIndex; $i++) {
                    $colIdx = $i * $colsPerDay + 2;
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $this->sheet->setCellValue("{$col}{$row}", $days[$i]);
                    $this->sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                    if (!$isClassroom) {
                        $sCol = Coordinate::stringFromColumnIndex($colIdx + 1);
                        $this->sheet->setCellValue("{$sCol}{$row}", "S");
                        $this->sheet->getStyle("{$sCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                        $this->sheet->getColumnDimension($sCol)->setWidth(8); // Lab-A gibi isimler sığacak kadar dar kalsın
                    }
                }
                $this->sheet->getColumnDimension('A')->setWidth(12); // Saat sütunu genişliği (Örn: 08:00 - 08:50 sığması için)
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
                $this->sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $row++;

                // Slotları yaz
                foreach ($slots as $slot) {
                    $timeLabel = $slot['slotStartTime']->format('H:i') . " - " . $slot['slotEndTime']->format('H:i');
                    $this->sheet->setCellValue("A{$row}", $timeLabel);
                    $this->sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                    for ($i = 0; $i <= $maxDayIndex; $i++) {
                        $colIdx = $i * $colsPerDay + 2;
                        $col = Coordinate::stringFromColumnIndex($colIdx);
                        $dayKey = 'day' . $i;

                        if (isset($slot['days'][$dayKey]) && $slot['days'][$dayKey] !== null) {
                            $items = is_array($slot['days'][$dayKey]) ? $slot['days'][$dayKey] : [$slot['days'][$dayKey]];

                            $combinedContent = new RichText();
                            $combinedClassroom = new RichText();

                            // Dikey padding için en başa boş satır
                            $combinedContent->createText("\n");
                            $combinedClassroom->createText("\n");

                            foreach ($items as $idx => $item) {
                                $this->formatScheduleItemForExport(
                                    $item,
                                    $scheduleFilter['type'],
                                    $showOptions,
                                    $combinedContent,
                                    $combinedClassroom,
                                    $idx > 0
                                );
                            }

                            // Dikey padding için en sona boş satır
                            $combinedContent->createText("\n");
                            $combinedClassroom->createText("\n");

                            $this->sheet->setCellValue("{$col}{$row}", $combinedContent);
                            if (!$isClassroom) {
                                $sCol = Coordinate::stringFromColumnIndex($colIdx + 1);
                                $this->sheet->setCellValue("{$sCol}{$row}", $combinedClassroom);
                                $this->sheet->getStyle("{$sCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                                $this->sheet->getStyle("{$sCol}{$row}")->getAlignment()->setWrapText(true);
                            }
                        }

                        $this->sheet->getStyle("{$col}{$row}")->getAlignment()->setWrapText(true);
                        $this->sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $this->sheet->getStyle("{$col}{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    }
                    $row++;
                }

                // Kenarlıklar
                $this->sheet->getStyle($firstCell . ":" . $lastCol . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $row += 2;
            }
        }

        // Sütun genişlikleri
        foreach ($this->sheet->getColumnIterator('A', $lastCol) as $column) {
            $colIdx = $column->getColumnIndex();
            // S sütunları zaten manuel set edildiği için sadece diğerlerini autoSize yap
            if ($this->sheet->getColumnDimension($colIdx)->getWidth() <= 0) {
                $this->sheet->getColumnDimension($colIdx)->setAutoSize(true);
            }
        }

        $exportFileName = $filters['academic_year'] . " " . $filters['semester'] . " " . ($scheduleFilter['file_title'] ?? 'Program') . ".xlsx";
        $this->downloadExportFile($exportFileName);
    }

    /**
     * ScheduleItem içeriğini kullanıcı tercihlerine göre formatlar (Zengin Metin Olarak)
     */
    private function formatScheduleItemForExport(ScheduleItem $item, string $scheduleType, array $options, RichText &$richContent, RichText &$richClassroom, bool $addSeparator = false): void
    {
        $slotDatas = $item->getSlotDatas();

        foreach ($slotDatas as $index => $data) {
            if ($addSeparator || $index > 0) {
                $richContent->createText("\n" . str_repeat('═', 20) . "\n");
                $richClassroom->createText("\n" . str_repeat('═', 5) . "\n");
                $addSeparator = false; // Sadece bir kez ekle
            }

            // Ders Adı
            $lessonName = $data->lesson->name;
            if ($options['show_code'] && !empty($data->lesson->code)) {
                $lessonName = "[" . $data->lesson->code . "] " . $lessonName;
            }
            $richContent->createTextRun($lessonName)->getFont()->setBold(true);

            // Hoca Adı
            if ($options['show_lecturer'] && $scheduleType !== 'user' && $data->lecturer) {
                $richContent->createText("\n(" . $data->lecturer->getFullName() . ")");
            }

            // Program/Bölüm Adı
            if ($options['show_program'] && ($scheduleType === 'user' || $scheduleType === 'classroom') && $data->lesson->program) {
                $richContent->createText("\n(" . $data->lesson->program->name . ")");
            }

            // Derslik (Ayrı sütuna gidecek)
            if ($scheduleType !== 'classroom' && $data->classroom) {
                $richClassroom->createText($data->classroom->name);
            }
        }
    }


    public function createFileTitle($filters): int
    {
        // Türüne (ders/sınav) göre maxDayIndex'i alıp genişliği hesapla
        $type = in_array($filters['type'] ?? '', ['midterm-exam', 'final-exam', 'makeup-exam']) ? 'exam' : 'lesson';
        $maxDayIndex = getSettingValue('maxDayIndex', $type, 4);

        // Eğer derslik programı ise her güne 1 sütun, değilse 2 sütun (ders + S)
        $colsPerDay = ($filters['owner_type'] === 'classroom') ? 1 : 2;
        $totalCols = ($maxDayIndex + 1) * $colsPerDay + 1;
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);

        // Üniversite ve dönem bilgileri
        $this->sheet->setCellValue('A2', 'GİRESUN ÜNİVERSİTESİ TİREBOLU MEHMET BAYRAK MESLEK YÜKSEKOKULU');
        $this->sheet->mergeCells("A2:{$lastCol}2");
        $this->sheet->getStyle("A2:{$lastCol}2")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("A2:{$lastCol}2")->getFont()->setBold(true)->setSize(12);

        $this->sheet->setCellValue('A3', $filters['academic_year'] . ' AKADEMİK YILI ' . mb_strtoupper($filters['semester']) . ' DÖNEMİ HAFTALIK DERS PROGRAMI');
        $this->sheet->mergeCells("A3:{$lastCol}3");
        $this->sheet->getStyle("A3:{$lastCol}3")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true)->setSize(12);

        return 6;
    }

    #[NoReturn]
    private function downloadExportFile($fileName = "schedule.xlsx"): void
    {
        // Tarayıcıya çıktı olarak gönder
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8 ');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($this->exportFile, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    /**
     * Export schedules as ICS calendar file compatible with Google and Apple Calendar
     * @throws Exception
     */
    #[NoReturn]
    public function exportScheduleIcs($filters = []): void
    {
        $timezone = new \DateTimeZone('Europe/Istanbul');
        $now = new \DateTime('now', $timezone);

        // Akademik dönem için başlangıç ve bitiş tarihleri (ayarlar sayfasından)
        $startDateStr = getSettingValue('lesson_start_date', 'lesson');
        $endDateStr = getSettingValue('lesson_end_date', 'lesson');
        $semesterStart = null;
        $semesterEnd = null;
        if (!empty($startDateStr) && !empty($endDateStr)) {
            try {
                $semesterStart = new \DateTime($startDateStr, $timezone);
                $semesterEnd = new \DateTime($endDateStr, $timezone);
            } catch (\Throwable $e) {
                $semesterStart = null;
                $semesterEnd = null;
            }
        }

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//schedulemaker//TR MBMYO Ders Programı//TR';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';

        foreach ($this->generateScheduleFilters($filters) as $scheduleFilter) {
            // Fetch schedule with items for the filter
            $schedule = (new \App\Models\Schedule())->get()
                ->where($scheduleFilter['filter'])
                ->with("items")
                ->first();

            if (!$schedule || empty($schedule->items))
                continue;

            foreach ($schedule->items as $scheduleItem) {
                $startText = $scheduleItem->getShortStartTime();
                $endText = $scheduleItem->getShortEndTime();

                if (empty($startText) || empty($endText))
                    continue;

                $dayIndex = $scheduleItem->day_index;
                $slotDatas = $scheduleItem->getSlotDatas();

                foreach ($slotDatas as $data) {
                    $lesson = $data->lesson;
                    $lecturer = $data->lecturer;
                    $classroom = $data->classroom;

                    // Compute first occurrence date
                    $useRecurrence = ($semesterStart instanceof \DateTime) && ($semesterEnd instanceof \DateTime) && ($semesterEnd >= $semesterStart);
                    if ($useRecurrence) {
                        $targetDow = $dayIndex + 1; // 1=Mon ... 7=Sun
                        $startDow = (int) $semesterStart->format('N');
                        $delta = ($targetDow - $startDow + 7) % 7;
                        $firstDate = (clone $semesterStart)->modify("+{$delta} days");
                        $dtStart = new \DateTime($firstDate->format('Y-m-d') . ' ' . $startText, $timezone);
                        $dtEnd = new \DateTime($firstDate->format('Y-m-d') . ' ' . $endText, $timezone);
                    } else {
                        $anchor = new \DateTime('next monday', $timezone);
                        if ((int) $now->format('N') === 1) {
                            $anchor = new \DateTime('today', $timezone);
                        }
                        $eventDate = (clone $anchor)->modify("+{$dayIndex} day");
                        $dtStart = new \DateTime($eventDate->format('Y-m-d') . ' ' . $startText, $timezone);
                        $dtEnd = new \DateTime($eventDate->format('Y-m-d') . ' ' . $endText, $timezone);
                    }

                    // Build summary (using a simplified version of formatScheduleItemForExport logic)
                    $summaryParts = [$lesson->name];
                    if ($scheduleFilter['type'] !== 'user' && $lecturer)
                        $summaryParts[] = "(" . $lecturer->getFullName() . ")";
                    if ($classroom)
                        $summaryParts[] = "[" . $classroom->name . "]";
                    $summary = implode(" ", $summaryParts);

                    $descriptionParts = [];
                    $descriptionParts[] = $scheduleFilter['title'];
                    $descriptionParts[] = 'Akademik Yıl: ' . $filters['academic_year'];
                    $descriptionParts[] = 'Dönem: ' . $filters['semester'];
                    $description = implode(' | ', array_filter($descriptionParts));

                    $uid = uniqid('schedulemaker-', true) . '@schedulemaker';
                    $dtstamp = $now->format('Ymd\THis');
                    $dtstartLine = 'DTSTART;TZID=Europe/Istanbul:' . $dtStart->format('Ymd\THis');
                    $dtendLine = 'DTEND;TZID=Europe/Istanbul:' . $dtEnd->format('Ymd\THis');

                    $lines[] = 'BEGIN:VEVENT';
                    $lines[] = 'UID:' . $uid;
                    $lines[] = 'DTSTAMP:' . $dtstamp;
                    $lines[] = $dtstartLine;
                    $lines[] = $dtendLine;
                    if ($useRecurrence) {
                        $weekdayCodes = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
                        $byday = $weekdayCodes[$dayIndex] ?? 'MO';
                        $untilLocal = (clone $semesterEnd)->setTime(23, 59, 59);
                        $untilUtc = (clone $untilLocal)->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
                        $lines[] = 'RRULE:FREQ=WEEKLY;UNTIL=' . $untilUtc . ';BYDAY=' . $byday;
                    }
                    $lines[] = 'SUMMARY:' . $this->escapeIcsText($summary);
                    if ($classroom)
                        $lines[] = 'LOCATION:' . $this->escapeIcsText($classroom->name);
                    if (!empty($description))
                        $lines[] = 'DESCRIPTION:' . $this->escapeIcsText($description);
                    $lines[] = 'END:VEVENT';
                }
            }
        }

        $lines[] = 'END:VCALENDAR';
        $content = implode("\r\n", $lines) . "\r\n";

        $fileName = $filters['academic_year'] . ' ' . $filters['semester'] . ' ' . 'ders-programi.ics';
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        echo $content;
        exit;
    }

    private function escapeIcsText(string $text): string
    {
        $text = str_replace(["\\", ",", ";", "\n", "\r"], ["\\\\", "\\,", "\\;", "\\n", ""], $text);
        return $text;
    }
}