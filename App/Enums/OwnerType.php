<?php

namespace App\Enums;

enum OwnerType: string
{
    case LESSON = 'lesson';
    case USER = 'user';
    case CLASSROOM = 'classroom';
    case PROGRAM = 'program';
}
