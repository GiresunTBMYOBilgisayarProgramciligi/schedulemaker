<?php

namespace App\Enums;

enum ClassroomType: int
{
    case CLASSROOM = 1;
    case COMPUTER_LAB = 2;
    case REMOTE_EDUCATION = 3;
    case HYBRID = 4;

    public function label(): string
    {
        return match($this) {
            self::CLASSROOM => 'Derslik',
            self::COMPUTER_LAB => 'Bilgisayar Laboratuvarı',
            self::REMOTE_EDUCATION => 'Uzaktan Eğitim Sınıfı',
            self::HYBRID => 'Karma',
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
