<?php

namespace App\Mailers;

use App\Core\Mailer;
use App\Models\User;

class ScheduleMailer extends Mailer
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param User $lecturer
     * @param array $changes
     * @return bool
     * @throws \Exception
     */
    public function sendScheduleChangesNotification(User $lecturer, array $changes): bool
    {
        if (empty($lecturer->mail)) {
            return false;
        }

        $this->mailer->addAddress($lecturer->mail, $lecturer->getFullName());
        $this->mailer->Subject = 'Ders/Sınav Programınızda Değişiklik Yapıldı';

        $changesHtml = "<ul>";
        foreach ($changes as $change) {
            $changesHtml .= "<li>" . htmlspecialchars($change->detail) . " (" . htmlspecialchars($change->created_at) . ")</li>";
        }
        $changesHtml .= "</ul>";

        $body = <<<HTML
        <h2>Sayın {$lecturer->getFullName()},</h2>
        <p>Programınızda aşağıdaki değişiklikler yapılmıştır:</p>
        {$changesHtml}
        <p>Lütfen sistem üzerinden güncel programınızı kontrol ediniz.</p>
        <p><a href="{$this->getAppUrl()}">Sisteme Giriş Yap</a></p>
        HTML;

        $this->mailer->Body = $body;
        $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</li>', '</p>'], "\n", $body));

        return $this->send();
    }

    private function getAppUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $domain;
    }
}
