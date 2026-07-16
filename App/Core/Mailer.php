<?php

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use function App\Helpers\getSettingValue;
use App\Core\Log;

/**
 * Uygulamanın e-posta gönderim işlemlerini sağlayan temel sınıf.
 */
abstract class Mailer
{
    protected PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->setup();
    }

    /**
     * PHPMailer ayarlarını veritabanından çekerek yapılandırır.
     */
    protected function setup(): void
    {
        try {
            // Sunucu ayarları
            $this->mailer->isSMTP();
            $this->mailer->Host       = getSettingValue('smtp_host', 'mail', 'localhost');
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = getSettingValue('smtp_user', 'mail', '');
            $this->mailer->Password   = getSettingValue('smtp_pass', 'mail', '');
            $this->mailer->SMTPSecure = getSettingValue('smtp_secure', 'mail', PHPMailer::ENCRYPTION_STARTTLS);
            $this->mailer->Port       = getSettingValue('smtp_port', 'mail', 587);
            $this->mailer->CharSet    = 'UTF-8';

            // Gönderici bilgileri
            $fromEmail = getSettingValue('mail_from', 'mail', 'noreply@localhost');
            $fromName  = getSettingValue('mail_from_name', 'mail', 'Schedule Maker');
            $this->mailer->setFrom($fromEmail, $fromName);
            
            // HTML içeriği
            $this->mailer->isHTML(true);

        } catch (Exception $e) {
            Log::getInstance()->error("Mailer yapılandırma hatası: {$e->getMessage()}");
        }
    }

    /**
     * E-postayı gönderir. Alt sınıflar içerik ve alıcı atamalarını yaptıktan sonra bu metodu çağırır.
     * 
     * @return bool Gönderim başarılıysa true, aksi halde false.
     */
    public function send(): bool
    {
        try {
            return $this->mailer->send();
        } catch (Exception $e) {
            Log::getInstance()->error("E-posta gönderme hatası: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
}
