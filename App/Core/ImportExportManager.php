<?php

namespace App\Core;

use App\Controllers\DepartmentController;
use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Models\User;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ImportExportManager
{
    public Spreadsheet $importFile;
    public Spreadsheet $exportFile;

    public function __construct(array $uploadedFile)
    {
        $this->uploadImportFile($uploadedFile['file']);
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
                'name' => $name,
                'last_name' => $last_name,
                'role' => array_search($role, $userController->getRoleList()),
                'department_id' => $departmentController->getDepartmentByName($department_name)->id ?? null,
                'program_id' => $programController->getProgramByName($program_name)->id ?? null,
            ];
            $user = $userController->getUserByEmail($mail);
            if ($user) {
                $user->fill($userData);
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
            "errors" => $errors
        ];
    }
}