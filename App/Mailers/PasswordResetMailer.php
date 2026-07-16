<?php

namespace App\Mailers;

use App\Core\Mailer;
use App\Models\User;

class PasswordResetMailer extends Mailer
{
    /**
     * Şifre sıfırlama bağlantısını içeren e-postayı gönderir.
     * 
     * @param User $user
     * @param string $token
     * @return bool
     */
    public function sendResetLink(User $user, string $token): bool
    {
        try {
            $this->mailer->addAddress($user->mail, $user->getFullName());
            $this->mailer->Subject = 'Şifre Sıfırlama İsteği';

            // Şablonu bir view dosyasından alıyoruz
            ob_start();
            $resetLink = $this->getAppUrl() . "/auth/resetpassword?token=" . urlencode($token) . "&email=" . urlencode($user->mail);
            extract(['user' => $user, 'resetLink' => $resetLink]);
            require $_ENV['VIEWS_PATH'] . '/emails/password_reset.php';
            $body = ob_get_clean();

            $this->mailer->Body = $body;

            return $this->send();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Geçerli uygulamanın kök URL'sini döndürür.
     */
    private function getAppUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $domainName;
    }
}
