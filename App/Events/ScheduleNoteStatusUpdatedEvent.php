<?php

namespace App\Events;

use App\Models\ScheduleNote;
use App\Models\User;

/**
 * Hoca notunun durumu veya geri bildirim mesajı güncellendiğinde fırlatılan olay (event).
 */
class ScheduleNoteStatusUpdatedEvent
{
    public function __construct(
        public ScheduleNote $note,
        public User $lecturer,
        public User $editor
    ) {
    }
}
