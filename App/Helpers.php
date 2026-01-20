<?php
//todo App/Helpers içerisine taşınabilir.
namespace App\Helpers;

use App\Controllers\LessonController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use Exception;

/**
 * @param mixed $default İstenen ayar bulunamazsa dönülecek ön tanımlı değer
 * @throws Exception
 */
function getSettingValue($key = null, $group = "general", $default = null)
{
    $settingsController = new SettingsController();
    $setting = $settingsController->getSetting($key, $group);
    if (is_null($setting))
        return $default;
    return match ($setting?->type) {
        'integer' => (int) $setting->value,
        'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
        'json' => json_decode($setting->value, true),
        default => $setting?->value
    };
}

/**
 * "Yıl ve dönem bilgisini arada bir boşluk olacak şekilde veriri örn: 2025-2026 Güz
 * @return bool|string
 * @throws Exception
 */
function getCurrentYearAndSemester(): bool|string
{
    try {
        return getSettingValue('academic_year') . " " . getSettingValue('semester');
    } catch (Exception $e) {
        throw new Exception("Semester/Dönem Bilgisi oluşturulurken hata oluştu");
    }
}

/**
 * Belirtilen döneme göre uygun dönem numaraları listesini döner
 * @param string|null $semester
 * @return array
 * @throws Exception
 */
function getSemesterNumbers(?string $semester = null): array
{
    // Eğer parametre verilmemişse ayarlar tablosundan al
    $semester = $semester ?? getSettingValue('semester');

    // Geçerli dönem sayısını al
    $semester_count = (new LessonController())->getMaxSemesterNo() ?? 4;

    // Güz döneminde **tek**, Bahar döneminde **çift** sayılar seçilmeli
    return array_values(array_filter(range(1, $semester_count), function ($semester_no) use ($semester) {
        return match ($semester) {
            'Güz' => $semester_no % 2 === 1, // Tek sayılar
            'Bahar' => $semester_no % 2 === 0, // Çift sayılar
            default => true, // Varsayılan: Tüm dönemleri döndür
        };
    }));

}

function getClassFromSemesterNo($semesterNo): string
{
    return match (true) {
        $semesterNo < 3 => 1,
        $semesterNo < 5 => 2,
        $semesterNo < 7 => 3,
        $semesterNo >= 9 => 4,
    };
}


/**
 * bir dizi içerisinde belirtilen string ile başlayan ilk anahtarı döner. Yoksa null döner
 * @param array $array
 * @param string $prefix
 * @return string|null
 */
function find_key_starting_with(array $array, string $prefix): ?string
{
    foreach ($array as $key => $value) {
        if (str_starts_with($key, $prefix)) {
            // Anahtar bulundu, tam adını döndür.
            return $key;
        }
    }
    // Anahtar bulunamadı.
    return null;
}


/**
 * Uygulamanın versiyon numarasını döner
 * @return string
 */
function getAppVersion(): string
{
    $composerFile = __DIR__ . '/../composer.json';
    if (!file_exists($composerFile)) {
        return '0.0.0';
    }
    $composerData = json_decode(file_get_contents($composerFile), true);
    return $composerData['version'] ?? '0.0.0';
}

/**
 * Ders isimlerini Türkçe kurallarına ve Roman rakamlarına uygun şekilde formatlar.
 * @param string|null $name
 * @return string
 */
function formatLessonName(?string $name): string
{
    if (empty($name))
        return "";

    // Roman rakamları listesi (I'den XII'ye kadar sık kullanılanlar)
    $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    // Kelime parçalarını formatlayan iç yardımcı fonksiyon.
    $formatPart = function ($part) use ($romanNumerals) {
        if (empty($part))
            return "";

        // Roman rakamı kontrolü (noktalama temizlenmiş haliyle)
        $cleanPart = trim($part, ".,;:/");
        $upperPart = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $cleanPart), "UTF-8");

        if (in_array($upperPart, $romanNumerals)) {
            // Kelime içindeki roman rakamı kısmını büyük yap, gerisini (noktalama) koru
            return str_ireplace($cleanPart, $upperPart, $part);
        }

        // Türkçe Title Case (Her kelimenin ilk harfi büyük)
        $firstChar = mb_substr($part, 0, 1, "UTF-8");
        $rest = mb_substr($part, 1, null, "UTF-8");

        // İlk harf i/ı ise düzelt
        if ($firstChar === 'i')
            $firstChar = 'İ';
        elseif ($firstChar === 'ı')
            $firstChar = 'I';
        else
            $firstChar = mb_strtoupper($firstChar, "UTF-8");

        // Kalan harfler küçültülür (İ/I düzeltmeleriyle)
        $rest = str_replace(['İ', 'I'], ['i', 'ı'], $rest);
        $rest = mb_strtolower($rest, "UTF-8");

        return $firstChar . $rest;
    };

    $words = explode(' ', $name);
    foreach ($words as &$word) {
        if (empty($word))
            continue;

        // Parantez içindeki grup belirteçlerini kontrol et: (A), (B), (ME) vb.
        if (preg_match('/^\((.+)\)$/', $word, $matches)) {
            $inner = $matches[1];
            // İçerideki harfi büyüt (tr-TR)
            $inner = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $inner), "UTF-8");
            $word = "(" . $inner . ")";
            continue;
        }

        // Kelime içinde tire (-) varsa parçalara ayırıp her parçayı formatla
        if (str_contains($word, '-')) {
            $parts = explode('-', $word);
            $formattedParts = array_map($formatPart, $parts);
            $word = implode('-', $formattedParts);
        } else {
            $word = $formatPart($word);
        }
    }
    return implode(' ', $words);
}
