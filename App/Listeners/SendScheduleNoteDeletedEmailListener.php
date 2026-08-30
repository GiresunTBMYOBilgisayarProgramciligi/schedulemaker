<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ScheduleNoteDeletedEvent;
use App\Mailers\ScheduleNoteMailer;
use App\Core\Log;

class SendScheduleNoteDeletedEmailListener
{
    /**
     * Olayı dinleyip silinme bildirim e-postasını gönderir.
     * 
     * @param ScheduleNoteDeletedEvent $event
     */
    public function handle(ScheduleNoteDeletedEvent $event): void
    {
        try {
            if (empty($event->lecturer->mail)) {
                return;
            }

            $mailer = new ScheduleNoteMailer();
            $mailer->sendNoteDeletedEmail($event->note, $event->lecturer, $event->deletedBy);
        } catch (\Throwable $e) {
            Log::logger()->error("SendScheduleNoteDeletedEmailListener hatası: " . $e->getMessage(), [
                'note_id'   => $event->note->id ?? null,
                'exception' => $e
            ]);
        }
    }
}
