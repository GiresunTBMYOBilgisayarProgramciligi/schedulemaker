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
use App\Models\User;
use Exception;
use Monolog\Logger;
use JetBrains\PhpStorm\NoReturn;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSettingValue;

class ImportExportManager
{
    private Spreadsheet $importFile;
    private Spreadsheet $exportFile;

    private $sheet;
    private array $formData = [];

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
        foreach ($rows as $index => $row) {
            [$mail, $title, $name, $last_name, $role, $department_name, $program_name] = array_map(function ($item) {
                return trim((string) ($item ?? ''));
            }, $row);

            if (empty($mail) or empty($title) or empty($name) or empty($last_name) or empty($role)) {
                $errors[] = "Satır " . ($index + 2) . ": Eksik veri!";
                $errorCount++;
                continue;
            }
            $userData = [
                'mail' => $mail,
                'title' => $title,
                'name' => mb_convert_case($name, MB_CASE_TITLE, "UTF-8"),
                'last_name' => mb_strtoupper($last_name, "UTF-8"),
                'role' => array_search($role, $userController->getRoleList()),
                'department_id' => $departmentController->getDepartmentByName($department_name)->id ?? null,
                'program_id' => $programController->getProgramByName($program_name)->id ?? null,
            ];
            $user = $userController->getUserByEmail($mail);
            if ($user) {
                $user->fill($userData);
                $user->password = null;
                $userController->updateUser($user);
                $updatedCount++;
            } else {
                $userController->saveNew($userData);
                $addedCount++;
            }
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
        foreach ($rows as $rowIndex => $row) {
            $hasError = false;// her bir satıra hatasız başlanıyor
            [$department_name, $program_name, $semester_no, $type, $code, $group_no, $name, $hours, $size, $lecturer_full_name, $classroom_type] = array_map(function ($item) {
                return trim((string) ($item ?? ''));
            }, $row);

            // Değişkenleri bir diziye topla
            $data = [$department_name, $program_name, $semester_no, $type, $code, $group_no, $name, $hours, $size, $lecturer_full_name, $classroom_type];

            // Her bir değeri kontrol et
            foreach ($data as $dataIndex => $value) {
                if ($value === null || $value === "") {
                    $errors[] = "Satir " . ($rowIndex + 2) . ": " . $expectedHeaders[$dataIndex] . ". sütunda eksik veri!";
                    $errorCount++;
                    $hasError = true; // Bu satırda hata olduğunu belirt
                }
            }

            $department = $departmentController->getDepartmentByName($department_name);
            $program = $programController->getProgramByName($program_name);
            $lecturer = $userController->getUserByFullName($lecturer_full_name);
            if (!$lecturer) {
                $errors[] = "Satır " . ($rowIndex + 2) . ": Hoca hatalı!" . $lecturer_full_name;
                $errorCount++;
                $hasError = true; // Bu satırda hata olduğunu belirt
            } elseif (!$program) {
                $errors[] = "Satır " . ($rowIndex + 2) . ": Program hatalı!" . $program_name;
                $errorCount++;
                $hasError = true; // Bu satırda hata olduğunu belirt
            } elseif (!$department) {
                $errors[] = "Satır " . ($rowIndex + 2) . ": Bölüm hatalı!" . $department_name;
                $errorCount++;
                $hasError = true; // Bu satırda hata olduğunu belirt
            }

            // Eğer bu satırda hata varsa, bir sonraki satıra geç
            if (isset($hasError) && $hasError) {
                $errors[] = "has_Error:" . $hasError;
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
                            "semester_no" => ['in' => $semesterNumbers],
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
                                "semester_no" => ['in' => $semesterNumbers],
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
                            "semester_no" => ['in' => $semesterNumbers],
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
                                "semester_no" => ['in' => $semesterNumbers],
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
                            "semester_no" => ['in' => $semesterNumbers],
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
                                "semester_no" => ['in' => $semesterNumbers],
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
        $filters = (new FilterValidator())->validate($filters, "exportScheduleAction");

        $scheduleController = new ScheduleController();
        /**
         * Dosya başlığı yazıldıktan sonra dosyada kaçıncı satırdan veri yazılmaya başlanacağını belirtir.
         */
        $row = $this->createFileTitle($filters);

        foreach ($this->generateScheduleFilters($filters) as $scheduleFilter) {
            // programların her biri için tablo oluşturuluyor
            $scheduleArray = $scheduleController->createScheduleExcelTable($scheduleFilter['filter']);// her bir elemanı bir satır olan bir dizi
            if (!$scheduleArray)
                continue; //Eğer programda ders yoksa geç
            $lastCellLetter = $scheduleFilter['type'] == "classroom" ? 'F' : 'K';
            //start::Program başlığını yaz
            $this->sheet->setCellValue("A{$row}", $scheduleFilter['title']);
            $this->sheet->getStyle("A{$row}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)  // Yatay ortalama
                ->setVertical(Alignment::VERTICAL_CENTER);    // Dikey ortalama
            $this->sheet->mergeCells("A{$row}:{$lastCellLetter}{$row}");
            $this->sheet->getStyle("A{$row}:{$lastCellLetter}{$row}")->getFont()->setBold(true);
            $this->sheet->getStyle("A{$row}:{$lastCellLetter}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('ffbf00');
            //end::Program başlığını yaz
            //Ders programının yazılmaya başlandığı ilk hücre çerçeve için kullanılacak
            $firstCell = "A" . $row + 1;
            $row++;// başlıktan sonra bir satır aşağı iniyoruz


            foreach ($scheduleArray as $scheduleRow) {
                $colNames = range('A', 'Z');
                $colNameIndex = 0;
                foreach ($scheduleRow as $scheduleCell) {
                    // Mevcut hücre referansını alalım
                    $currentCell = $colNames[$colNameIndex] . "{$row}";
                    $cellValue = "";
                    if (is_array($scheduleCell)) {
                        //bu hücrede ders var demektir
                        if (isset($scheduleCell[0]) and is_array($scheduleCell[0])) {
                            // bu alanda gruplu iki ders var demektir.
                            /**
                             * Hücre içerisine yazdırılacak derslerin bilgilerinin dizisi
                             */
                            $lessons = [];
                            foreach ($scheduleCell as $groupLesson) {
                                if (isset($groupLesson['lesson_id'])) {
                                    $lesson = (new Lesson())->find($groupLesson['lesson_id']);
                                    // ders bilgileri hücreye yazılır.
                                    $lessons[] = $this->setExportLessonName($lesson, $scheduleFilter['type']);
                                }
                                if (isset($groupLesson['classroom_id']) and $scheduleFilter['type'] != "classroom") {
                                    $classroom = (new Classroom())->find($groupLesson['classroom_id']);
                                    // derslik bilgileri hücreye yazılır.
                                    $lessons[] = $classroom->name;
                                }
                            }
                            // hücre içerisine ders bilgileri satırlar oluşturacak şekilde yazılır
                            $cellValue = implode("\n", $lessons);
                        } else {
                            // Bu hücrede tek bir ders var demektir
                            if (isset($scheduleCell['lesson_id'])) {
                                $lesson = (new Lesson())->find($scheduleCell['lesson_id']);
                                // ders bilgileri hücreye yazılır.
                                $cellValue = $this->setExportLessonName($lesson, $scheduleFilter['type']);
                            }
                            if (isset($scheduleCell['classroom_id']) and $scheduleFilter['type'] != "classroom") {
                                $classroom = (new Classroom())->find($scheduleCell['classroom_id']);
                                // derslik bilgileri hücreye yazılır.
                                $cellValue = $classroom->name;
                            }
                        }
                    } else {
                        //burada bir ders yok boş hücre yada gün ce saat bilgisini içerir
                        $cellValue = $scheduleCell ?? "";
                    }
                    $this->sheet->setCellValue($currentCell, $cellValue);// ders bilgisi

                    // Hücre stilini düzenleyerek satır sonunu işleme
                    $this->sheet->getStyle($currentCell)->getAlignment()->setWrapText(true);
                    $this->sheet->getStyle($currentCell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $this->sheet->getStyle($currentCell)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    // bir sonraki sütüna geç
                    $colNameIndex++;
                }
                //bir satır yazıldıktan sonra sonraki satıra geç
                $row++;
            }
            // Her hücreye kenarlık ekle
            $lastCell = $lastCellLetter . $row - 1; //todo maxDay Index e göre ayarlanmalı
            $this->sheet->getStyle($firstCell . ":" . $lastCell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            //todo Cuma yada perşembe günü ders yoksa G sütünunda bitiyor. k olarak belirtilirse tüm hafta kenarlık oluyor
            $row += 2;//bir tablo bittikten sonra iki satır boşluk bırak
        }


        // Sütunları otomatik boyutlandır (içeriğe göre ayarla)
        foreach ($this->sheet->getColumnIterator('A', 'K') as $column) {
            $colIndex = $column->getColumnIndex(); // A, B, C...
            $this->sheet->getColumnDimension($colIndex)->setAutoSize(true);
        }
        $exportFileName = $filters['academic_year'] . " " . $filters['semester'] . " " . $scheduleFilter['file_title'] . ".xlsx";
        $this->downloadExportFile($exportFileName);

    }

    /**
     * @throws Exception
     */
    private function setExportLessonName(Lesson $lesson, $scheduleType)
    {
        return match ($scheduleType) {
            'user' => $lesson->name . "\n (" . $lesson->getProgram()->name . ")",
            'classroom' => $lesson->name . " (" . $lesson->getProgram()->name . ") \n (" . $lesson->getLecturer()->getFullName() . ")",
            'program' => $lesson->name . " \n (" . $lesson->getLecturer()->getFullName() . ")",
            default => $lesson->name,
        };
    }

    public function createFileTitle($filters): int
    {
        $lastCellLetter = $filters['owner_type'] == "classroom" ? 'F' : 'K';
        // Üniversite ve dönem bilgileri
        $this->sheet->setCellValue('A2', 'GİRESUN ÜNİVERSİTESİ TİREBOLU MEHMET BAYRAK MESLEK YÜKSEKOKULU');
        $this->sheet->mergeCells("A2:{$lastCellLetter}2");
        // Birleştirilmiş hücrede yazıyı ortalama (hem yatay hem dikey)
        $this->sheet->getStyle("A2:{$lastCellLetter}2")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)  // Yatay ortalama
            ->setVertical(Alignment::VERTICAL_CENTER);    // Dikey ortalama
        $this->sheet->getStyle("A2:{$lastCellLetter}2")->getFont()->setBold(true);

        $this->sheet->setCellValue('A3', $filters['academic_year'] . ' AKADEMİK YILI ' . mb_strtoupper($filters['semester']) . ' DÖNEMİ HAFTALIK DERS PROGRAMI');
        $this->sheet->mergeCells("A3:{$lastCellLetter}3");
        // Birleştirilmiş hücrede yazıyı ortalama (hem yatay hem dikey)
        $this->sheet->getStyle("A3:{$lastCellLetter}3")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)  // Yatay ortalama
            ->setVertical(Alignment::VERTICAL_CENTER);    // Dikey ortalama
        $this->sheet->getStyle("A3:{$lastCellLetter}3")->getFont()->setBold(true);
        return 6; //başlıktan sonra devam edilecek satır numarası
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
            // Fetch schedules matching the filter
            $schedules = (new \App\Models\Schedule())->get()->where($scheduleFilter['filter'])->all();
            if (count($schedules) === 0)
                continue;

            foreach ($schedules as $schedule) {
                //$this->logger()->debug("Schedule time: ", ['schedule' => $schedule]);
                if (empty($schedule->time))
                    continue;
                // Parse start-end times from schedule time label like "08.00 - 08.50"
                [$startText, $endText] = array_map('trim', explode('-', str_replace(' - ', '-', $schedule->time)));
                $startText = str_replace('.', ':', trim($startText));
                $endText = str_replace('.', ':', trim($endText));

                for ($dayIndex = 0; $dayIndex <= getSettingValue('maxDayIndex', 'lesson', 4); $dayIndex++) {
                    $day = $schedule->{"day{$dayIndex}"};
                    if (is_null($day) || $day === false)
                        continue;

                    // Determine lessons: either single associative array or array of associative arrays (grouped)
                    $entries = (isset($day[0]) && is_array($day[0])) ? $day : [$day];
                    foreach ($entries as $entry) {
                        if (!isset($entry['lesson_id']))
                            continue;
                        $lesson = (new \App\Models\Lesson())->find($entry['lesson_id']);
                        if (!$lesson)
                            continue;
                        $classroomName = '';
                        if (isset($entry['classroom_id'])) {
                            $classroom = (new \App\Models\Classroom())->find($entry['classroom_id']);
                            if ($classroom)
                                $classroomName = $classroom->name;
                        }

                        // Compute first occurrence date for this weekday based on settings
                        $useRecurrence = ($semesterStart instanceof \DateTime) && ($semesterEnd instanceof \DateTime) && ($semesterEnd >= $semesterStart);
                        if ($useRecurrence) {
                            $targetDow = $dayIndex + 1; // 1=Mon ... 7=Sun
                            $startDow = (int) $semesterStart->format('N');
                            $delta = ($targetDow - $startDow + 7) % 7;
                            $firstDate = (clone $semesterStart)->modify("+{$delta} days");
                            $dtStart = new \DateTime($firstDate->format('Y-m-d') . ' ' . $startText, $timezone);
                            $dtEnd = new \DateTime($firstDate->format('Y-m-d') . ' ' . $endText, $timezone);
                        } else {
                            // Fallback: single reference week next Monday + dayIndex
                            $anchor = new \DateTime('next monday', $timezone);
                            if ((int) $now->format('N') === 1) {
                                $anchor = new \DateTime('today', $timezone);
                            }
                            $eventDate = (clone $anchor)->modify("+{$dayIndex} day");
                            $dtStart = new \DateTime($eventDate->format('Y-m-d') . ' ' . $startText, $timezone);
                            $dtEnd = new \DateTime($eventDate->format('Y-m-d') . ' ' . $endText, $timezone);
                        }

                        // Build summary based on context
                        $summary = $this->setExportLessonName($lesson, $scheduleFilter['type']);
                        $location = $classroomName;
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
                        if ($location !== '')
                            $lines[] = 'LOCATION:' . $this->escapeIcsText($location);
                        if ($description !== '')
                            $lines[] = 'DESCRIPTION:' . $this->escapeIcsText($description);
                        $lines[] = 'END:VEVENT';
                    }
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