<?php

namespace App\Services;

use App\Core\Log;
use App\Models\User;
use App\Services\Schedule\ScheduleService;
use App\DTOs\UserDTO;
use App\Repositories\UserRepository;
use App\Core\Database;
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
        $this->logger->info('Yeni kullanıcı ekleniyor', ['mail' => $dto->mail]);

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
        $this->logger->info('Kullanıcı güncelleniyor', ['id' => $user->id]);

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
     * Bu orkestrasyon sayesinde Model, Servis katmanından bağımsız hale getirilmiştir.
     * 
     * @param User $user Silinecek kullanıcı nesnesi
     * @throws Exception
     */
    public function deleteUser(User $user): void
    {
        $this->logger->info('Kullanıcı siliniyor', ['id' => $user->id]);

        try {
            Database::transaction(function () use ($user) {
                // Önce kullanıcıya ait ders programı kayıtlarını (çakışmaları önlemek için) temizle
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
     * @param array $loginData ['mail', 'password', 'remember_me']
     * @throws Exception Yanlış şifre veya kullanıcı bulunamadı
     */
    public function login(array $loginData): void
    {
        $loginData = (object) $loginData;
        $userRepository = new UserRepository();
        $user = $userRepository->findByEmail($loginData->mail);

        if (!$user) {
            throw new Exception("Kullanıcı kayıtlı değil");
        }

        if (!password_verify($loginData->password, $user->password)) {
            throw new Exception("Şifre Yanlış");
        }

        // Session veya Cookie yaz
        if (!$loginData->remember_me) {
            $_SESSION[$_ENV['SESSION_KEY']] = $user->id;
        } else {
            setcookie($_ENV['COOKIE_KEY'], $user->id, [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        // Log giriş
        $this->logger->info($user->getFullName() . ' giriş yaptı.', Log::context($this, [
            'user_id' => $user->id,
            'username' => $user->getFullName(),
        ]));

        // last_login güncelle
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user->id]);
    }
}
