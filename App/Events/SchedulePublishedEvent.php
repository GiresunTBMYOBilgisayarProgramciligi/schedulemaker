<?php

namespace App\Events;

class SchedulePublishedEvent
{
    public function __construct(
        public int $scheduleId
    ) {}
}
