<?php

namespace App\Enums;

/**
 * Akademik ünvanları temsil eden Backed Enum.
 */
enum UserTitle: string
{
    case ResAssist = 'Araş. Gör.';
    case Lecturer = 'Öğr. Gör.';
    case DrLecturer = 'Öğr. Gör. Dr.';
    case AsstProf = 'Dr. Öğr. Üyesi';
    case AssocProf = 'Doç. Dr.';
    case Prof = 'Prof. Dr.';

    /**
     * Tüm ünvanları uzunluklarına göre azalan (en uzundan en kısaya) şekilde sıralı olarak döndürür.
     * Bu işlem parseAcademicName metodu içerisinde uzun ünvanların ("Öğr. Gör. Dr." gibi) kısa olanlardan ("Öğr. Gör." gibi)
     * önce kontrol edilerek daha doğru ayıklanmasını sağlar.
     * 
     * @return array<string>
     */
    public static function getSortedByLength(): array
    {
        $titles = array_map(fn($case) => $case->value, self::cases());
        
        usort($titles, function ($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        return $titles;
    }
}
