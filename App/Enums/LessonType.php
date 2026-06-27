<?php

namespace App\Enums;

enum LessonType: int
{
    case COMPULSORY = 1;
    case ELECTIVE = 2;
    case UNIVERSITY_ELECTIVE = 3;
    case INTERNSHIP = 4;

    public function label(): string
    {
        return match($this) {
            self::COMPULSORY => 'Zorunlu',
            self::ELECTIVE => 'Seçmeli',
            self::UNIVERSITY_ELECTIVE => 'Üniversite Seçmeli',
            self::INTERNSHIP => 'Staj',
        };
    }
}
