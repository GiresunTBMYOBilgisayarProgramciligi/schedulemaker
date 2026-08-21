<?php

namespace App\Mailers;

use App\Core\Mailer;
use App\Core\View;
use App\Models\Schedule;
use App\Models\User;
use Exception;

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
     */
    public function sendScheduleChangesNotification(User $lecturer, array $changes): bool
    {
        try {
            if (empty($lecturer->mail)) {
                return false;
            }

            $this->mailer->addAddress($lecturer->mail, $lecturer->getFullName());
            $this->mailer->Subject = 'Ders/Sınav Programınızda Değişiklik Yapıldı';

            $body = View::renderEmail('schedule_changes', [
                'lecturer' => $lecturer,
                'changes'  => $changes,
                'appUrl'   => $this->getAppUrl()
            ]);

            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</li>', '</p>'], "\n", $body));

            return $this->send();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param User $lecturer
     * @param Schedule $schedule
     * @param string $excelContent
     * @param string $icsContent
     * @return bool
     */
    public function sendSchedulePublishedNotification(
        User $lecturer,
        Schedule $schedule,
        string $excelContent,
        string $icsContent
    ): bool {
        try {
            if (empty($lecturer->mail)) {
                return false;
            }

            $this->mailer->addAddress($lecturer->mail, $lecturer->getFullName());
            $academicYear = htmlspecialchars($schedule->academic_year ?? '');
            $semester     = htmlspecialchars($schedule->semester ?? '');
            $this->mailer->Subject = "{$academicYear} {$semester} Dönemi Ders Programınız Yayınlandı";

            $fileNameBase = ($schedule->academic_year ?? '') . '-' . ($schedule->semester ?? '') . '-ders-programi';
            $fileNameBase = preg_replace('~[^-\w]+~', '_', $fileNameBase);

            // Excel Eki
            if (!empty($excelContent)) {
                $this->mailer->addStringAttachment(
                    $excelContent,
                    $fileNameBase . '.xlsx',
                    'base64',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                );
            }

            // ICS Eki
            if (!empty($icsContent)) {
                $this->mailer->addStringAttachment(
                    $icsContent,
                    $fileNameBase . '.ics',
                    'base64',
                    'text/calendar; charset=utf-8'
                );
            }

            $body = View::renderEmail('schedule_published', [
                'lecturer' => $lecturer,
                'schedule' => $schedule,
                'appUrl'   => $this->getAppUrl()
            ]);

            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</li>', '</p>', '</tr>'], "\n", $body));

            return $this->send();
        } catch (Exception $e) {
            return false;
        }
    }

    private function getAppUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $domain;
    }
}
