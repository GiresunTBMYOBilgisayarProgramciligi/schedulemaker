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

    /**
     * Ünvan Ad Soyad şeklinde verilen ismi ünvan, ad, soyad şeklinde ayırarak bir dizi döndürür
     * @param string $fullName
     * @return array
     */
    public static function parseAcademicName(string $fullName): array
    {
        // Ünvanları uzunluklarına göre sırala (en uzundan en kısaya)
        $titles = self::getSortedByLength();

        $title = '';
        $nameLastName = '';

        // Ünvanları kontrol et
        foreach ($titles as $possibleTitle) {
            if (strpos($fullName, $possibleTitle) === 0) {
                $title = $possibleTitle;
                // Ünvanı kaldır ve trim yap
                $nameLastName = trim(substr($fullName, strlen($possibleTitle)));
                break;
            }
        }

        // Eğer ünvan bulunamadıysa tüm stringi isim soyisim olarak al
        if (empty($title)) {
            $nameLastName = trim($fullName);
        }

        // Ad ve soyadı ayır - son kelime soyadı olacak
        $nameParts = explode(' ', $nameLastName);
        $lastName = array_pop($nameParts); // Son kelimeyi al (soyad)
        $name = implode(' ', $nameParts); // Kalan kısmı ad olarak birleştir

        return [
            'title' => $title,
            'name' => $name,
            'last_name' => $lastName
        ];
    }
}
