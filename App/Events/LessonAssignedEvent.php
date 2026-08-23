<?php

namespace App\Events;

use App\Models\LessonAssignment;

class LessonAssignedEvent
{
    public function __construct(
        public LessonAssignment $assignment
    ) {
    }
}
