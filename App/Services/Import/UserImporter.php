<?php

namespace App\Services\Import;

use App\Core\Database;
use App\Core\Log;
use App\Repositories\UserRepository;
use App\Enums\UserRole;
use App\Enums\UserTitle;
use App\Services\UserService;
use Exception;
use Monolog\Logger;
use App\Repositories\DepartmentRepository;
use App\Repositories\ProgramRepository;
use App\Repositories\UnitRepository;
use App\Core\Gate;
use App\Enums\PermissionType;
use App\Validators\UserValidator;
use App\Exceptions\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Models\User;

/**
 * Excel dosyasından kullanıcıları içe aktarır.
 */
class UserImporter
{
    private \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet;
    private array $cache = [
        'departments' => [],
        'programs'    => [],
        'units'       => [],
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
        $userRepository        = new UserRepository();
        $userService           = new UserService();


        $addedUsers   = [];
        $updatedUsers = [];
        $errorCount   = 0;
        $errors       = [];

        $rows = $this->sheet->toArray();

        // Başlık satırını al ve doğrula
        $headers = array_shift($rows);
        $expectedHeaders = ["Mail", "Ünvanı", "Adı", "Soyadı", "Görevi", "Fakülte / MYO / Enstitü", "Bölümü / Ana Bilim Dalı", "Programı / Bilim Dalı"];
        $headers = array_map(fn($item) => is_string($item) ? trim($item) : $item, $headers);
        $headers = array_values(array_filter($headers, fn($item) => !is_null($item) && $item !== ''));

        if ($headers !== $expectedHeaders) {
            throw new Exception("Excel başlıkları beklenen formatta değil!");
        }

        Database::transaction(function () use ($rows, &$errorCount, &$errors, &$addedUsers, &$updatedUsers, $userRepository, $userService) {
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

                [$mail, $title, $name, $last_name, $role, $unit_name, $department_name, $program_name] = array_map(
                    fn($item) => trim((string) ($item ?? '')),
                    $row
                );

                // Caching Unit
                $unit = null;
                if (!empty($unit_name)) {
                    if (!isset($this->cache['units'][$unit_name])) {
                        $this->cache['units'][$unit_name] = (new UnitRepository())->findByName($unit_name) ?: false;
                    }
                    $unit = $this->cache['units'][$unit_name];
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

                $rowErrors = [];
                if (!empty($unit_name) && $unit === false) {
                    $rowErrors[] = "Birim bulunamadı! ({$unit_name})";
                }
                if (!empty($department_name) && $department === false) {
                    $rowErrors[] = "Bölüm bulunamadı! ({$department_name})";
                }
                if (!empty($program_name) && $program === false) {
                    $rowErrors[] = "Program bulunamadı! ({$program_name})";
                }

                // Compatibility Check
                if ($unit && $department && $department->unit_id !== $unit->id) {
                    $rowErrors[] = "Uyumsuzluk: {$unit->name} biriminin {$department->name} bölümü yok!";
                } elseif (empty($unit_name) && !empty($department_name)) {
                    $rowErrors[] = "Uyumsuzluk: Bölüm belirtildiğinde birim de belirtilmelidir!";
                }
                if ($department && $program && $program->department_id !== $department->id) {
                    $rowErrors[] = "Uyumsuzluk: {$department->name} bölümünün {$program->name} programı yok!";
                } elseif (empty($department_name) && !empty($program_name)) {
                    $rowErrors[] = "Uyumsuzluk: Program belirtildiğinde bölüm de belirtilmelidir!";
                }

                $roleEnum = UserRole::fromLabel($role);
                $titleEnum = UserTitle::tryFrom($title);

                $userData = [
                    'mail'          => $mail,
                    'title'         => $titleEnum?->value ?? $title,
                    'name'          => mb_convert_case($name, MB_CASE_TITLE, "UTF-8"),
                    'last_name'     => mb_strtoupper($last_name, "UTF-8"),
                    'role'          => $roleEnum?->value ?? $role,
                    'unit_id'       => $unit ? $unit->id : null,
                    'department_id' => $department ? $department->id : null,
                    'program_id'    => $program ? $program->id : null,
                ];

                $userDTO = null;
                try {
                    $userValidator = new UserValidator();
                    $userDTO = $userValidator->getDTO($userData);
                } catch (ValidationException $e) {
                    foreach ($e->getValidationErrors() as $field => $msg) {
                        $rowErrors[] = $msg;
                    }
                }

                if (!empty($rowErrors)) {
                    $errors[] = "Satır " . ($index + 2) . ": " . implode(" | ", $rowErrors);
                    $errorCount++;
                    continue;
                }

                if (!isset($this->cache['users_by_mail'][$mail])) {
                    $this->cache['users_by_mail'][$mail] = $userRepository->findByEmail($mail);
                }
                $user = $this->cache['users_by_mail'][$mail];

                if ($user) {
                    if (!Gate::check(PermissionType::UPDATE->value, $user)) {
                        $rowErrors[] = "Bu kullanıcıyı güncelleme yetkiniz yok.";
                    }
                } else {
                    if (!Gate::check(PermissionType::CREATE->value, User::class, $userDTO)) {
                        $rowErrors[] = "Bu birime/bölüme kullanıcı ekleme yetkiniz yok.";
                    }
                }

                if (!empty($rowErrors)) {
                    $errors[] = "Satır " . ($index + 2) . ": " . implode(" | ", $rowErrors);
                    $errorCount++;
                    continue;
                }

                if ($user) {
                    $user->fill($userData);
                    $user->password = null;
                    $userService->updateUser($user);
                    $updatedUsers[$user->id] = $user->getFullName();
                } else {
                    $userId = $userService->saveNew($userDTO);
                    $addedUsers[$userId] = $userData['name'] . ' ' . $userData['last_name'];
                    $this->cache['users_by_mail'][$mail] = $userRepository->findByEmail($mail);
                }
            }

            $username = $this->logContext()['username'] ?? "Sistem";
            $this->logger()->info(
                "{$username} Excel'den kullanıcıları içe aktardı. Eklendi: " . count($addedUsers) . ", Güncellendi: " . count($updatedUsers) . ", Hatalı: {$errorCount}",
                $this->logContext()
            );
        });

        return [
            "status"       => "success",
            "added"        => count($addedUsers),
            "updated"      => count($updatedUsers),
            "errorCount"   => $errorCount,
            "errors"       => $errors,
            "addedUsers"   => $addedUsers,
            "updatedUsers" => $updatedUsers,
        ];
    }
}
