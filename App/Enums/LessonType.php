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

    /**
     * Enum listesini [value => label] şeklinde dizi olarak döner.
     * @return array<int, string>
     */
    public static function toArray(): array
    {
        $list = [];
        foreach (self::cases() as $case) {
            $list[$case->value] = $case->label();
        }
        return $list;
    }

    /**
     * Label üzerinden Enum örneğini döndürür.
     * @param string $label
     * @return self|null
     */
    public static function fromLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if (mb_strtolower($case->label(), 'UTF-8') === mb_strtolower(trim($label), 'UTF-8')) {
                return $case;
            }
        }
        return null;
    }
}
