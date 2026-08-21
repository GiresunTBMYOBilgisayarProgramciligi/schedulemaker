<?php

namespace App\Mailers;

use App\Core\Mailer;
use App\Core\View;
use App\Enums\ScheduleNoteStatus;
use App\Models\ScheduleNote;
use App\Models\User;
use Exception;

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

            // Görüldü veya Beklemede durumlarında e-posta bildirimi gönderilmez
            if ($note->status === ScheduleNoteStatus::READ->value || $note->status === ScheduleNoteStatus::PENDING->value) {
                return false;
            }

            $this->mailer->addAddress($lecturer->mail, $lecturer->getFullName());
            $this->mailer->Subject = 'Ders Programı İstek Durumu: ' . $note->getStatusEnum()->getLabel();

            $body = View::renderEmail('schedule_note_feedback', [
                'note'     => $note,
                'lecturer' => $lecturer,
                'editor'   => $editor
            ]);

            $this->mailer->Body = $body;

            return $this->send();
        } catch (Exception $e) {
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

            $body = View::renderEmail('schedule_note_deleted', [
                'note'      => $note,
                'lecturer'  => $lecturer,
                'deletedBy' => $deletedBy
            ]);

            $this->mailer->Body = $body;

            return $this->send();
        } catch (Exception $e) {
            return false;
        }
    }
}
