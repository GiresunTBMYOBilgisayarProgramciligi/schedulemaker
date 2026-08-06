<?php

namespace App\Events;

class ScheduleChangesNotifiedEvent
{
    /**
     * @var array
     */
    public array $changes;

    /**
     * @var int
     */
    public int $lecturer_id;

    public function __construct(int $lecturer_id, array $changes)
    {
        $this->lecturer_id = $lecturer_id;
        $this->changes = $changes;
    }
}
