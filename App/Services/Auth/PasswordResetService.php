<?php

namespace App\Services\Auth;

use App\Services\BaseService;
use App\DTOs\ForgotPasswordDTO;
use App\DTOs\ResetPasswordDTO;
use App\Repositories\UserRepository;
use App\Repositories\PasswordResetRepository;
use App\Core\EventDispatcher;
use App\Events\UserForgotPasswordEvent;
use Exception;

class PasswordResetService extends BaseService
{
    /**
     * E-posta adresi için token oluşturur, kaydeder ve mail olayını tetikler.
     * 
     * @param ForgotPasswordDTO $dto
     * @throws Exception
     */
    public function sendResetLink(ForgotPasswordDTO $dto): void
    {
        $userRepository = new UserRepository();
        $user = $userRepository->findByEmail($dto->email);

        if (!$user) {
            // Güvenlik gereği kullanıcı bulunamadıysa da hata vermiyoruz (bilgi sızdırmamak için).
            return;
        }

        $token = bin2hex(random_bytes(32));
        
        $resetRepo = new PasswordResetRepository();

        // Eski tokenları sil
        $resetRepo->deleteByEmail($dto->email);

        // Yeni tokenı ekle
        $resetRepo->createToken($dto->email, $token);

        // Olayı tetikle (Event Dispatcher aracılığıyla)
        $event = new UserForgotPasswordEvent($user, $token);
        EventDispatcher::getInstance()->dispatch($event);

        $this->logger->info("Şifre sıfırlama talebi alındı", ['email' => $dto->email]);
    }

    /**
     * Token'ı doğrular ve kullanıcının şifresini günceller.
     * 
     * @param ResetPasswordDTO $dto
     * @throws Exception
     */
    public function resetPassword(ResetPasswordDTO $dto): void
    {
        $resetRepo = new PasswordResetRepository();
        
        // Token'ı ve süresini kontrol et
        $record = $resetRepo->findValidToken($dto->email, $dto->token);

        if (!$record) {
            throw new Exception("Geçersiz veya süresi dolmuş şifre sıfırlama bağlantısı.");
        }

        $userRepository = new UserRepository();
        $user = $userRepository->findByEmail($dto->email);

        if (!$user) {
            throw new Exception("Kullanıcı bulunamadı.");
        }

        // Şifreyi güncelle (User repository veya service kullanılabilir ancak BaseService yapısına uygun update)
        $hashedPassword = password_hash($dto->password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $user->id]);

        // Kullanılmış token'ı sil
        $resetRepo->deleteByEmail($dto->email);

        $this->logger->info("Kullanıcı şifresi sıfırlandı", ['user_id' => $user->id]);
    }
}
