<?php

namespace App\Mailers;

use App\Core\Mailer;
use App\Models\ScheduleNote;
use App\Models\User;

class ScheduleNoteMailer extends Mailer
{
    /**
     * Akademisyene not durumu güncellemesini e-posta ile gönderir.
     * 
     * @param ScheduleNote $note
     * @param User $lecturer
     * @param User $editor
     * @return bool
     */
    public function sendStatusFeedbackEmail(ScheduleNote $note, User $lecturer, User $editor): bool
    {
        try {
            if (empty($lecturer->mail)) {
                return false;
            }

            $this->mailer->addAddress($lecturer->mail, $lecturer->getFullName());
            $this->mailer->Subject = 'Ders Programı İstek Durumu: ' . $note->getStatusEnum()->getLabel();

            ob_start();
            extract([
                'note' => $note,
                'lecturer' => $lecturer,
                'editor' => $editor
            ]);
            $viewsPath = $_ENV['VIEWS_PATH'] ?? dirname(__DIR__) . '/Views';
            require $viewsPath . '/emails/schedule_note_feedback.php';
            $body = ob_get_clean();

            $this->mailer->Body = $body;

            return $this->send();
        } catch (\Exception $e) {
            if (ob_get_level() > 0) ob_end_clean();
            return false;
        }
    }

    /**
     * Akademisyene notunun silindiğini e-posta ile bildirir.
     */
    public function sendNoteDeletedEmail(ScheduleNote $note, User $lecturer, User $deletedBy): bool
    {
        try {
            if (empty($lecturer->mail)) {
                return false;
            }

            $this->mailer->addAddress($lecturer->mail, $lecturer->getFullName());
            $this->mailer->Subject = 'Ders Programı Notunuz Silindi';

            ob_start();
            extract([
                'note' => $note,
                'lecturer' => $lecturer,
                'deletedBy' => $deletedBy
            ]);
            $viewsPath = $_ENV['VIEWS_PATH'] ?? dirname(__DIR__) . '/Views';
            require $viewsPath . '/emails/schedule_note_deleted.php';
            $body = ob_get_clean();

            $this->mailer->Body = $body;

            return $this->send();
        } catch (\Exception $e) {
            if (ob_get_level() > 0) ob_end_clean();
            return false;
        }
    }
}
