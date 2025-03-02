<?php

namespace App\Core;

use App\Controllers\ClassroomController;
use App\Controllers\DepartmentController;
use App\Controllers\LessonController;
use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Models\Lesson;
use App\Models\User;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ImportExportManager
{
    private Spreadsheet $importFile;
    private Spreadsheet $exportFile;

    private array $formData = [];

    public function __construct(array $uploadedFile, array $formData = [])
    {
        $this->uploadImportFile($uploadedFile['file']);
        $this->formData = $formData;
    }

    public function uploadImportFile(array $uploadedFile): void
    {
        //todo upload işlemleri
        // türk kontrolü
        $this->importFile = IOFactory::load($uploadedFile['tmp_name']);
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
        // Excel dosyasını aç

        $sheet = $this->importFile->getActiveSheet();
        $rows = $sheet->toArray();
        // Başlık satırını al ve doğrula
        $headers = array_shift($rows);
        $expectedHeaders = ["Mail", "Ünvanı", "Adı", "Soyadı", "Görevi", "Bölümü", "Programı"];

        if ($headers !== $expectedHeaders) {
            throw new Exception("Excel başlıkları beklenen formatta değil!");
        }
        foreach ($rows as $index => $row) {
            [$mail, $title, $name, $last_name, $role, $department_name, $program_name] = array_map('trim', $row);

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
                $user = new User();
                $user->fill($userData);
                $userController->saveNew($user);
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
        $addedCount = 0;
        $updatedCount = 0;
        $errorCount = 0;
        $errors = [];
        // Excel dosyasını aç

        $sheet = $this->importFile->getActiveSheet();
        $rows = $sheet->toArray();
        // Başlık satırını al ve doğrula
        $headers = array_shift($rows);
        $headers = array_map('trim', $headers);
        $expectedHeaders =
            ["Bölüm", "Program", "Dönemi", "Türü", "Dersin Kodu", "Dersin Adı", "Saati", "Mevcudu", "Hocası", "Derslik türü"];


        if ($headers !== $expectedHeaders) {
            throw new Exception("Excel başlıkları beklenen formatta değil!");
        }
        if (!isset($this->formData['academic_year']) or !isset($this->formData['semester'])) {
            throw new Exception("Yıl veya dönem belirtilmemiş");
        }
        foreach ($rows as $rowIndex => $row) {
            [$department_name, $program_name, $semester_no, $type, $code, $name, $hours, $size, $lecturer_full_name, $classroom_type] = array_map('trim', $row);

            // Değişkenleri bir diziye topla
            $data = [$department_name, $program_name, $semester_no, $type, $code, $name, $hours, $size, $lecturer_full_name, $classroom_type];

            // Her bir değeri kontrol et
            foreach ($data as $dataIndex => $value) {
                if ($value === null || $value === "") {
                    $errors[] = "Satir " . ($rowIndex + 2) . ": " . ($dataIndex + 1) . ". sütunda eksik veri!";
                    $errorCount++;
                    $hasError = true; // Bu satırda hata olduğunu belirt
                }
            }

            $department = $departmentController->getDepartmentByName($department_name);
            $program = $programController->getProgramByName($program_name);
            $lecturer = $userController->getUserByFullName($lecturer_full_name);
            if (!$lecturer) {
                $errors[] = "Satır " . ($rowIndex + 2) . ": " . ($dataIndex + 1) . ". sütunda Hoca hatalı!" . $lecturer_full_name;
                $errorCount++;
                $hasError = true; // Bu satırda hata olduğunu belirt
            } elseif (!$program) {
                $errors[] = "Satır " . ($rowIndex + 2) . ": " . ($dataIndex + 1) . ". sütunda Program hatalı!" . $program_name;
                $errorCount++;
                $hasError = true; // Bu satırda hata olduğunu belirt
            } elseif (!$department) {
                $errors[] = "Satır " . ($rowIndex + 2) . ": " . ($dataIndex + 1) . ". sütunda Bölüm hatalı!" . $department_name;
                $errorCount++;
                $hasError = true; // Bu satırda hata olduğunu belirt
            }

            // Eğer bu satırda hata varsa, bir sonraki satıra geç
            if (isset($hasError) && $hasError) {
                continue;
            }
            $lessonData = [
                'code' => $code,
                'name' => $name,
                'size' => $size,
                'hours' => $hours,
                'type' => array_search(trim($type),(new LessonController())->getTypeList()),
                'semester_no' => $semester_no,
                'lecturer_id' => $lecturer->id,
                'department_id' => $department->id,
                'program_id' => $program->id,
                'semester' => $this->formData['semester'],
                'classroom_type' => array_search(trim($classroom_type), (new ClassroomController())->getTypeList()),
                'academic_year' => $this->formData['academic_year'],
            ];
            //Ders ders kodu, program_id ikilisine göre benzersiz kaydediliyor. Aynı ders koduna sahip dersler var
            $lesson = $lessonsController->getLessonByFilters(['code' => $code, 'program_id' => $program->id]);
            if ($lesson) {
                $lesson->fill($lessonData);
                $lessonsController->updateLesson($lesson);
                $updatedCount++;
            } else {
                $lesson = new Lesson();
                $lesson->fill($lessonData);
                $lessonsController->saveNew($lesson);
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
}