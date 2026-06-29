<?php

namespace App\Enums;

enum ScheduleItemStatus: string
{
    case SINGLE = 'single';
    case GROUP = 'group';
    case PREFERRED = 'preferred';
    case UNAVAILABLE = 'unavailable';
}
