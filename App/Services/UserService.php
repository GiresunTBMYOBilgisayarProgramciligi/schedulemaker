<?php

namespace App\Services;

use App\Core\Log;
use App\Models\User;
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
     * Şifreyi otomatik olarak hash'ler (girilmemişse varsayılan "123456").
     *
     * @param array $userData Kullanıcı verileri
     * @return int Oluşturulan kullanıcının ID'si
     * @throws Exception Duplicate e-posta veya kayıt hatası
     */
    public function saveNew(array $userData): int
    {
        $this->logger->info('Yeni kullanıcı ekleniyor', ['mail' => $userData['mail'] ?? null]);

        $userData['password'] = password_hash($userData['password'] ?? '123456', PASSWORD_DEFAULT);

        try {
            $user = new User();
            $user->fill($userData);
            $user->create();

            $this->logger->info('Kullanıcı eklendi', ['id' => $user->id]);
            return $user->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz.", (int) $e->getCode(), $e);
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
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
            $user->update($excluded);
            $this->logger->info('Kullanıcı güncellendi', ['id' => $user->id]);
            return $user->id;
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta adresi giriniz.", (int) $e->getCode(), $e);
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
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
        $stmt = $this->db->prepare("SELECT * FROM users WHERE mail = :mail");
        $stmt->bindParam(':mail', $loginData->mail);
        $stmt->execute();

        if (!$stmt) {
            throw new Exception("Hiçbir kullanıcı kayıtlı değil");
        }

        $user = $stmt->fetch(\PDO::FETCH_OBJ);
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
        $userObj = new User();
        $userObj->fill((array) $user);
        $this->logger->info($userObj->getFullName() . ' giriş yaptı.', Log::context($this, [
            'user_id' => $user->id,
            'username' => $userObj->getFullName(),
        ]));

        // last_login güncelle
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user->id]);
    }
}
