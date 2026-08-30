<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ScheduleNote;
use App\Models\User;

/**
 * Hoca notu silindiğinde fırlatılan olay (event).
 */
class ScheduleNoteDeletedEvent
{
    public function __construct(
        public ScheduleNote $note,
        public User $lecturer,
        public User $deletedBy
    ) {
    }
}
