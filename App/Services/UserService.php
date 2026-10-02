<?php

namespace App\Services;

use App\Core\Log;
use App\Models\User;
use App\Services\Schedule\ScheduleService;
use App\DTOs\UserDTO;
use App\DTOs\LoginDTO;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;
use App\DTOs\BulkActionResultDTO;
use App\Repositories\UserRepository;
use App\Core\Database;
use App\Core\Gate;
use App\Enums\PermissionType;
use App\Enums\UserRole;
use App\Enums\UserTitle;
use Exception;
use PDOException;

/**
 * Kullanıcı yönetimi iş mantığı servisi.
 *
 * Sorumluluklar:
 * - Kullanıcı CRUD işlemleri (saveNew, updateUser)
 * - Kimlik doğrulama (login, session/cookie yönetimi)
 */
class UserService extends BaseService
{
    private UserRepository $userRepository;

    public function __construct(?UserRepository $userRepository = null)
    {
        parent::__construct();
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    /**
     * Akademisyen personeli Birim -> Bölüm -> Unvan Kıdemi / Ad Soyad hiyerarşisinde sıralar.
     *
     * @param User[] $lecturers
     * @param bool $hasMultipleUnits
     * @return User[]
     */
    public function sortLecturersHierarchically(array $lecturers, bool $hasMultipleUnits = false): array
    {
        usort($lecturers, function (User $a, User $b) use ($hasMultipleUnits) {
            if ($hasMultipleUnits) {
                $unitA = $a->department?->unit?->name ?? $a->unit?->name ?? '';
                $unitB = $b->department?->unit?->name ?? $b->unit?->name ?? '';
                $unitCmp = strcmp($unitA, $unitB);
                if ($unitCmp !== 0) {
                    return $unitCmp;
                }
            }

            $deptA = $a->department?->name ?? '';
            $deptB = $b->department?->name ?? '';
            $deptCmp = strcmp($deptA, $deptB);
            if ($deptCmp !== 0) {
                return $deptCmp;
            }

            $rankA = UserTitle::tryFrom((string)$a->title)?->getHierarchyRank() ?? 0;
            $rankB = UserTitle::tryFrom((string)$b->title)?->getHierarchyRank() ?? 0;
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }

            return strcmp(
                mb_strtolower($a->name . ' ' . $a->last_name, 'UTF-8'),
                mb_strtolower($b->name . ' ' . $b->last_name, 'UTF-8')
            );
        });

        return $lecturers;
    }

    /**
     * Yetkili akademisyenleri çeker, sıralar ve birden fazla birim durumunu hesaplar.
     *
     * @param User|null $user
     * @param array $conditions
     * @return array{lecturers: User[], has_multiple_units: bool, department_count: int}
     * @throws Exception
     */
    public function getAuthorizedLecturersData(?User $user = null, array $conditions = []): array
    {
        $lecturers = $this->userRepository->getAuthorizedLecturersWithHierarchy($user, $conditions);

        $unitIds = array_unique(array_filter(array_map(
            fn($u) => $u->department?->unit_id ?? $u->unit_id,
            $lecturers
        )));
        $hasMultipleUnits = count($unitIds) > 1;

        $departmentIds = array_unique(array_filter(array_map(
            fn($u) => $u->department_id,
            $lecturers
        )));
        $departmentCount = count($departmentIds);

        $lecturers = $this->sortLecturersHierarchically($lecturers, $hasMultipleUnits);

        return [
            'lecturers' => $lecturers,
            'has_multiple_units' => $hasMultipleUnits,
            'department_count' => $departmentCount
        ];
    }

    /**
     * Hoca listesini <optgroup> (Birim), <option disabled> (Bölüm) ve <option> (Hoca)
     * hiyerarşisiyle girintili HTML olarak oluşturur.
     *
     * @param User[] $lecturers
     * @param int|null $selectedLecturerId
     * @param bool|null $hasMultipleUnits
     * @param string|null $emptyOptionLabel
     * @return string
     */
    public function renderLecturerSelectOptions(
        array $lecturers,
        ?int $selectedLecturerId = null,
        ?bool $hasMultipleUnits = null,
        ?string $emptyOptionLabel = '-- Atanmamış --'
    ): string {
        $html = '';
        if ($emptyOptionLabel !== null) {
            $html .= '<option value="">' . htmlspecialchars($emptyOptionLabel) . '</option>';
        }

        if (empty($lecturers)) {
            return $html;
        }

        if ($hasMultipleUnits === null) {
            $unitIds = array_unique(array_filter(array_map(
                fn($u) => $u->department?->unit_id ?? $u->unit_id,
                $lecturers
            )));
            $hasMultipleUnits = count($unitIds) > 1;
        }

        $departmentCount = count(array_unique(array_filter(array_map(
            fn($u) => $u->department_id,
            $lecturers
        ))));

        $currentUnit = null;
        $currentDept = null;

        foreach ($lecturers as $lec) {
            if ($hasMultipleUnits) {
                $unitName = $lec->department?->unit?->name ?? $lec->unit?->name ?? 'Diğer';
                $deptName = $lec->department?->name ?? '';

                if ($currentUnit !== $unitName) {
                    if ($currentUnit !== null) {
                        $html .= '</optgroup>';
                    }
                    $currentUnit = $unitName;
                    $currentDept = null;
                    $html .= '<optgroup label="' . htmlspecialchars($currentUnit) . '">';
                }

                if ($deptName !== '' && $deptName !== $currentDept) {
                    $currentDept = $deptName;
                    $html .= '<option disabled>&nbsp;&nbsp;' . htmlspecialchars($currentDept) . '</option>';
                }

                $indent = $deptName !== '' ? '&nbsp;&nbsp;&nbsp;&nbsp;' : '&nbsp;&nbsp;';
            } elseif ($departmentCount > 1) {
                $deptName = $lec->department?->name ?? 'Diğer';

                if ($currentDept !== $deptName) {
                    if ($currentDept !== null) {
                        $html .= '</optgroup>';
                    }
                    $currentDept = $deptName;
                    $html .= '<optgroup label="' . htmlspecialchars($currentDept) . '">';
                }

                $indent = '';
            } else {
                $indent = '';
            }

            $selected = ($selectedLecturerId !== null && (int)$selectedLecturerId === (int)$lec->id) ? ' selected' : '';
            $deptId = (int)($lec->department_id ?? 0);
            $deptAttr = ' data-department-id="' . $deptId . '"';
            $deptNameAttr = ' data-department-name="' . htmlspecialchars($lec->department?->name ?? 'Diğer') . '"';

            $html .= '<option value="' . $lec->id . '"' . $deptAttr . $deptNameAttr . $selected . '>'
                . $indent . htmlspecialchars($lec->getFullName(true))
                . '</option>';
        }

        if ($currentUnit !== null || (!$hasMultipleUnits && $currentDept !== null)) {
            $html .= '</optgroup>';
        }

        return $html;
    }
    // ──────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────

    /**
     * Yeni kullanıcı oluşturur.
     * Şifreyi otomatik olarak hash'ler (girilmemişse rastgele 16 karakterli bir şifre atanır).
     *
     * @param UserDTO $dto Doğrulanmış ve paketlenmiş kullanıcı verileri
     * @return int Oluşturulan kullanıcının ID'si
     * @throws Exception Duplicate e-posta veya kayıt hatası
     */
    public function saveNew(UserDTO $dto): int
    {
        $this->logger->debug('Yeni kullanıcı ekleniyor', ['mail' => $dto->mail]);

        $userData = $dto->toArray();
        $password = !empty($userData['password']) ? $userData['password'] : bin2hex(random_bytes(8));
        $userData['password'] = password_hash($password, PASSWORD_DEFAULT);

        try {
            return Database::transaction(function () use ($userData) {
                $user = new User();
                $user->fill($userData);
                $user->create();

                $this->logger->info('Kullanıcı eklendi', ['id' => $user->id]);

                return $user->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz.", (int) $e->getCode(), $e);
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Kullanıcı verilerini günceller (Controller'dan gelen DTO ile).
     * Model bulma ve doldurma işlemlerini kapsüller.
     *
     * @param int $id Güncellenecek kullanıcının ID'si
     * @param UserDTO $dto Yeni kullanıcı verileri
     * @return int Kullanıcının ID'si
     * @throws Exception
     */
    public function updateUserData(int $id, UserDTO $dto): int
    {
        $user = (new UserRepository())->find($id);
        if (!$user) {
            throw new Exception("Kullanıcı bulunamadı.");
        }

        // Mevcut modele DTO verilerini dolduruyoruz
        $user->fill(array_merge(['id' => $id], $dto->toArray()));

        if ($dto->password === null) {
            $user->password = null;
        }
        if ($dto->title === null) {
            $user->title = null;
        }
        if ($dto->departmentId === null) {
            $user->department_id = null;
        }
        if ($dto->programId === null) {
            $user->program_id = null;
        }
        if ($dto->unitId === null) {
            $user->unit_id = null;
        }

        return $this->updateUser($user);
    }

    /**
     * Mevcut kullanıcıyı günceller.
     * Şifre alanı boş gönderilirse güncelleme dışı bırakılır.
     *
     * @param User $user Güncellenecek User nesnesi
     * @return int Kullanıcının ID'si
     * @throws Exception Duplicate e-posta veya güncelleme hatası
     */
    public function updateUser(User $user): int
    {
        $this->logger->debug('Kullanıcı güncelleniyor', ['id' => $user->id]);

        $excluded = ['register_date', 'last_login'];

        if (!empty($user->password)) {
            $user->password = password_hash($user->password, PASSWORD_DEFAULT);
        } else {
            $excluded[] = 'password';
        }

        try {
            return Database::transaction(function () use ($user, $excluded) {
                $user->update($excluded);
                $this->logger->info('Kullanıcı güncellendi', ['id' => $user->id]);
                return $user->id;
            });
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz.", (int) $e->getCode(), $e);
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Kullanıcıyı sistemden siler.
     * Silme işleminden önce, kullanıcının ilişkili ders programlarını temizler.
     * 
     * @param User $user Silinecek kullanıcı nesnesi
     * @throws Exception
     */
    public function deleteUser(User $user): void
    {
        $this->logger->debug('Kullanıcı siliniyor', ['id' => $user->id]);

        try {
            Database::transaction(function () use ($user) {
                // Önce kullanıcıya ait ders programı kayıtlarını temizle
                (new ScheduleService())->wipeResourceSchedules('user', $user->id);
                
                // Sonra kullanıcıyı veritabanından sil
                $user->delete();
            });
            
            $this->logger->info('Kullanıcı başarıyla silindi', ['id' => $user->id]);
        } catch (Exception $e) {
            $this->logger->error('Kullanıcı silinirken hata oluştu', [
                'id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Kullanıcı silinirken bir hata oluştu: " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────
    // Kimlik Doğrulama
    // ──────────────────────────────────────────

    /**
     * Kullanıcı girişi yapar.
     * Giriş başarılıysa session veya cookie'ye kullanıcı ID'si yazılır,
     * last_login alanı güncellenir.
     *
     * @param LoginDTO|array $loginData ['mail', 'password', 'remember_me'] ya da LoginDTO
     * @throws Exception Yanlış şifre veya kullanıcı bulunamadı
     */
    public function login(LoginDTO|array $loginData): void
    {
        $dto = $loginData instanceof LoginDTO ? $loginData : LoginDTO::fromArray((array)$loginData);

        $userRepository = new UserRepository();
        $user = $userRepository->findByEmail($dto->mail);

        if (!$user) {
            throw new Exception("Kullanıcı kayıtlı değil");
        }

        if (!password_verify($dto->password, $user->password)) {
            throw new Exception("Şifre Yanlış");
        }

        $sessionKey = $_ENV['SESSION_KEY'] ?? 'schedule_session';
        $cookieKey  = $_ENV['COOKIE_KEY'] ?? 'schedule_cookie_';

        // Session yaz
        $_SESSION[$sessionKey] = $user->id;

        // Remember Me istenmişse güvenli imzalı çerez oluştur
        if ($dto->rememberMe) {
            $secret = $_ENV['APP_KEY'] ?? 'schedulemaker_app_secure_salt';
            $hmac = hash_hmac('sha256', $user->id . ':' . $user->password, $secret);
            $cookieValue = $user->id . ':' . $hmac;

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie($cookieKey, $cookieValue, [
                'expires'  => time() + (86400 * 30),
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        // Log giriş
        $this->logger->info($user->getFullName() . ' giriş yaptı.', Log::context($this, [
            'user_id'  => $user->id,
            'username' => $user->getFullName(),
        ]));

        // last_login güncelle
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user->id]);
    }

    // ──────────────────────────────────────────
    // Toplu İşlemler
    // ──────────────────────────────────────────

    /**
     * Birden fazla kullanıcıyı toplu siler.
     *
     * @param BulkDeleteDTO|array $dtoOrIds
     * @return BulkActionResultDTO
     */
    public function bulkDelete(BulkDeleteDTO|array $dtoOrIds): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkDeleteDTO ? $dtoOrIds : new BulkDeleteDTO(ids: array_map('intval', (array)$dtoOrIds));
        $this->logger->debug('Toplu kullanıcı silme başlatıldı', ['ids' => $dto->ids]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $user = (new User())->find($id);
                if (!$user) {
                    $failed[$id] = "Kullanıcı bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::DELETE->value, $user)) {
                    $failed[$id] = "Silme yetkiniz yok.";
                    continue;
                }

                $this->deleteUser($user);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu kullanıcı silme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }

    /**
     * Birden fazla kullanıcıyı toplu günceller.
     *
     * @param BulkUpdateDTO|array $dtoOrIds
     * @param array<string, mixed> $fields
     * @return BulkActionResultDTO
     */
    public function bulkUpdate(BulkUpdateDTO|array $dtoOrIds, array $fields = []): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkUpdateDTO
            ? $dtoOrIds
            : new BulkUpdateDTO(ids: array_map('intval', (array)$dtoOrIds), fields: $fields);

        $this->logger->debug('Toplu kullanıcı güncelleme başlatıldı', ['ids' => $dto->ids, 'fields' => $dto->fields]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $user = clone (new User())->find($id);
                if (!$user) {
                    $failed[$id] = "Kullanıcı bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::UPDATE->value, $user)) {
                    $failed[$id] = "Güncelleme yetkiniz yok.";
                    continue;
                }

                if (array_key_exists('role', $dto->fields) && !Gate::allowsRole('submanager')) {
                    $failed[$id] = "Kullanıcı rolünü değiştirme yetkiniz yok.";
                    continue;
                }

                foreach ($dto->fields as $fieldName => $fieldValue) {
                    $user->{$fieldName} = $fieldValue === '' ? null : $fieldValue;
                }

                // Şifre güncellenmemeli, null bırakılıyor
                $user->password = null;
                $this->updateUser($user);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu kullanıcı güncelleme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }
}
