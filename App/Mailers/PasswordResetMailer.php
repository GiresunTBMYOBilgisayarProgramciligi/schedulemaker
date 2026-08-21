<?php

namespace App\Mailers;

use App\Core\Mailer;
use App\Core\View;
use App\Models\User;
use Exception;

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

            $resetLink = $this->getAppUrl() . "/auth/resetpassword?token=" . urlencode($token) . "&email=" . urlencode($user->mail);
            $body = View::renderEmail('password_reset', [
                'user'      => $user,
                'resetLink' => $resetLink
            ]);

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
