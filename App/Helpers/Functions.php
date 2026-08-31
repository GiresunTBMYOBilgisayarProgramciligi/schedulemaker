<?php

namespace App\Helpers;

use App\Controllers\SettingsController;
use App\Core\Gate;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Unit;
use App\Models\User;
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
 * Belirtilen döneme ve maksimum yarıyıla göre uygun dönem numaraları listesini döner
 * @param string|null $semester
 * @param int|null $maxSemester
 * @return array
 * @throws Exception
 */
function getSemesterNumbers(?string $semester = null, ?int $maxSemester = null): array
{
    // Eğer parametre verilmemişse ayarlar tablosundan al
    $semester = $semester ?? getSettingValue('semester');

    // Geçerli dönem sayısını al
    if ($maxSemester === null) {
        $maxInDb = (new Lesson())->get()->max('semester_no');
        $semester_count = max(4, min(12, (int)$maxInDb));
    } else {
        $semester_count = max(1, $maxSemester);
    }

    // Güz döneminde **tek**, Bahar döneminde **çift** sayılar seçilmeli
    return array_values(array_filter(range(1, $semester_count), function ($semester_no) use ($semester) {
        return match ($semester) {
            'Güz' => $semester_no % 2 === 1, // Tek sayılar
            'Bahar' => $semester_no % 2 === 0, // Çift sayılar
            default => true, // Varsayılan: Tüm dönemleri döndür
        };
    }));
}

/**
 * Yarıyıl numarasına karşılık gelen sınıf numarasını döner (örn: 1, 2 -> '1', 3, 4 -> '2')
 * @param int|string $semesterNo
 * @return string
 */
function getClassFromSemesterNo($semesterNo): string
{
    return (string) max(1, (int) ceil((int) $semesterNo / 2));
}

/**
 * Verilen Program, Bölüm veya Birim için kayıtlı derslerin en yüksek semester_no değerini hesaplar.
 *
 * @param int|null $programId
 * @param int|null $departmentId
 * @param int|null $unitId
 * @return int Maksimum yarıyıl sayısı
 */
function getMaxSemesterNo(?int $programId = null, ?int $departmentId = null, ?int $unitId = null): int
{
    // 1. Program ID verilmişse: Program derslerinin maksimum semester_no değeri
    if (!empty($programId)) {
        $lessonMax = (new Lesson())->get()->where(['program_id' => $programId])->max('semester_no');
        if (!empty($lessonMax) && (int)$lessonMax > 0) {
            return (int)$lessonMax;
        }

        // Programda ders yoksa bağlı olduğu bölüm üzerinden bak
        if (empty($departmentId)) {
            $program = (new Program())->find($programId);
            if ($program && $program->department_id) {
                $departmentId = (int)$program->department_id;
            }
        }
    }

    // 2. Bölüm ID verilmişse: Bölüm derslerinin maksimum semester_no değeri
    if (!empty($departmentId)) {
        $lessonMax = (new Lesson())->get()->where(['department_id' => $departmentId])->max('semester_no');
        if (!empty($lessonMax) && (int)$lessonMax > 0) {
            return (int)$lessonMax;
        }

        // Bölümde ders yoksa bağlı olduğu birim üzerinden bak
        if (empty($unitId)) {
            $department = (new Department())->find($departmentId);
            if ($department && $department->unit_id) {
                $unitId = (int)$department->unit_id;
            }
        }
    }

    // 3. Birim ID verilmişse: Birime ait bölümlerdeki derslerin maksimum semester_no değeri
    if (!empty($unitId)) {
        $departments = (new Department())->get()->where(['unit_id' => $unitId])->all();
        $deptIds = array_column($departments, 'id');
        if (!empty($deptIds)) {
            $lessonMax = (new Lesson())->get()->where(['department_id' => ['in' => $deptIds]])->max('semester_no');
            if (!empty($lessonMax) && (int)$lessonMax > 0) {
                return (int)$lessonMax;
            }
        }

        // Birimde henüz ders tanımlanmamışsa birim tipine göre varsayılan
        $unit = (new Unit())->find($unitId);
        if ($unit && $unit->type === 'myo') {
            return 4;
        }
    }

    // 4. Genel veritabanındaki maksimum ders yarıyılı veya varsayılan 8
    $maxInDb = (new Lesson())->get()->max('semester_no');
    return (!empty($maxInDb) && (int)$maxInDb > 0) ? (int)$maxInDb : 8;
}

/**
 * Yarıyıl/Sınıf seçimi için uygun dönem listesini döner.
 *
 * @param string|null $semester Güz, Bahar, Yaz veya null
 * @param int|null $maxSemester Maksimum yarıyıl sayısı (varsayılan 12)
 * @return array<int, string> [semester_no => 'X. Sınıf (Y. Yarıyıl)']
 */
function getSemesterSelectOptions(?string $semester = null, ?int $maxSemester = null): array
{
    $semester = $semester ?? getSettingValue('semester') ?? 'Güz';
    $maxSemester = $maxSemester ?? 12;
    $options = [];

    for ($i = 1; $i <= $maxSemester; $i++) {
        $matches = match ($semester) {
            'Güz' => $i % 2 === 1,
            'Bahar' => $i % 2 === 0,
            default => true,
        };

        if ($matches) {
            $classNo = getClassFromSemesterNo($i);
            $options[$i] = "{$classNo}. Sınıf ({$i}. Yarıyıl)";
        }
    }

    return $options;
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
    $composerFile = $_ENV['APP_PATH'] . '/../composer.json';
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

/**
 * Belirtilen kullanıcının veya aktif oturumdaki kullanıcının rol seviyesini kontrol eder.
 *
 * @param string|UserRole $role Gereken minimum rol (örn. 'secretary' veya UserRole::Secretary)
 * @param User|null $user Belirli bir kullanıcı (null ise aktif oturumdaki kullanıcı)
 * @param bool $reverse true ise belirtilen rolden daha düşük/eşit roller
 * @return bool
 */
function hasRole(string|UserRole $role, ?User $user = null, bool $reverse = false): bool
{
    return Gate::hasRole($user, $role, $reverse);
}
