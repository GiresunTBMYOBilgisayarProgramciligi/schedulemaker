<?php

namespace App\Services\Import;

use App\Controllers\DepartmentController;
use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Core\Database;
use App\Core\Log;
use App\Models\User;
use App\Services\UserService;
use Exception;
use Monolog\Logger;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Excel dosyasından kullanıcıları içe aktarır.
 */
class UserImporter
{
    private Spreadsheet $sheet;
    private array $cache = [
        'departments' => [],
        'programs'    => [],
        'users_by_mail' => [],
    ];

    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->sheet = $spreadsheet->getActiveSheet();
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
     * @return array{status: string, added: int, updated: int, errorCount: int, errors: array}
     * @throws Exception
     */
    public function import(): array
    {
        $userController        = new UserController();
        $userService           = new UserService();
        $departmentController  = new DepartmentController();
        $programController     = new ProgramController();

        $addedCount   = 0;
        $updatedCount = 0;
        $errorCount   = 0;
        $errors       = [];

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
                $isEmpty = true;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string) $cell) !== '') {
                        $isEmpty = false;
                        break;
                    }
                }
                if ($isEmpty) continue;

                [$mail, $title, $name, $last_name, $role, $department_name, $program_name] = array_map(
                    fn($item) => trim((string) ($item ?? '')),
                    $row
                );

                if (empty($mail) || empty($title) || empty($name) || empty($last_name) || empty($role)) {
                    $errors[] = "Satır " . ($index + 2) . ": Eksik veri!";
                    $errorCount++;
                    continue;
                }

                // Caching
                if (!isset($this->cache['departments'][$department_name])) {
                    $this->cache['departments'][$department_name] = $departmentController->getDepartmentByName($department_name);
                }
                $department = $this->cache['departments'][$department_name];

                $programCacheKey = $department_name . '_' . $program_name;
                if (!isset($this->cache['programs'][$programCacheKey])) {
                    $this->cache['programs'][$programCacheKey] = $programController->getProgramByName($program_name);
                }
                $program = $this->cache['programs'][$programCacheKey];

                if (!$department || !$program) {
                    $rowErrors = [];
                    if (!$department) $rowErrors[] = "Bölüm bulunamadı! ({$department_name})";
                    if (!$program)    $rowErrors[] = "Program bulunamadı! ({$program_name})";
                    $errors[] = "Satır " . ($index + 2) . ": " . implode(" | ", $rowErrors);
                    $errorCount++;
                    continue;
                }

                $userData = [
                    'mail'          => $mail,
                    'title'         => $title,
                    'name'          => mb_convert_case($name, MB_CASE_TITLE, "UTF-8"),
                    'last_name'     => mb_strtoupper($last_name, "UTF-8"),
                    'role'          => array_search($role, $userController->getRoleList()),
                    'department_id' => $department->id ?? null,
                    'program_id'    => $program->id ?? null,
                ];

                if (!isset($this->cache['users_by_mail'][$mail])) {
                    $this->cache['users_by_mail'][$mail] = $userController->getUserByEmail($mail);
                }
                $user = $this->cache['users_by_mail'][$mail];

                if ($user) {
                    $user->fill($userData);
                    $user->password = null;
                    $userService->updateUser($user);
                    $updatedCount++;
                } else {
                    $userService->saveNew($userData);
                    $addedCount++;
                    $this->cache['users_by_mail'][$mail] = $userController->getUserByEmail($mail);
                }
            }

            $db->commit();

            $username = $this->logContext()['username'] ?? "Sistem";
            $this->logger()->info(
                "{$username} Excel'den kullanıcıları içe aktardı. Eklendi: {$addedCount}, Güncellendi: {$updatedCount}, Hatalı: {$errorCount}",
                $this->logContext()
            );
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return [
            "status"     => "success",
            "added"      => $addedCount,
            "updated"    => $updatedCount,
            "errorCount" => $errorCount,
            "errors"     => $errors,
        ];
    }
}
