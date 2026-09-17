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
 * todo bu fonksiyon tek bir yerde kullanılıyor kaldırılabilir. 
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
 * Verilen dönem (Güz/Bahar) için geçerli yarıyıl numaralarını döner.
 * 3+1 ve 7+1 sistemlerinde son iki yarıyıl hem Güz hem Bahar döneminde yer alır.
 *
 * @param string|null $semester Güz, Bahar veya null
 * @param int|null $maxSemester Maksimum yarıyıl sayısı (veya null)
 * @param bool $includeInternshipSemesters 3+1 / 7+1 gibi staj sistemlerinde son iki yarıyılı her iki döneme de dahil et
 * @return array
 * @throws Exception
 */
function getSemesterNumbers(?string $semester = null, ?int $maxSemester = null, bool $includeInternshipSemesters = false): array
{
    // Eğer parametre verilmemişse ayarlar tablosundan al
    $semester = $semester ?? getSettingValue('semester');

    // Geçerli dönem sayısını al
    if ($maxSemester === null) {
        $maxInDb = (new Lesson())->get()->max('semester_no');
        $semester_count = max(4, min(12, (int) $maxInDb));
    } else {
        $semester_count = max(1, $maxSemester);
    }

    if ($includeInternshipSemesters && $semester_count < 4) {
        $semester_count = 4;
    }

    $lastTwo = ($includeInternshipSemesters && $semester_count >= 2)
        ? [$semester_count - 1, $semester_count]
        : [];

    // Güz döneminde tek, Bahar döneminde çift sayılar (staj dönemleri aktifse son iki yarıyıl her ikisinde de)
    return array_values(array_filter(range(1, $semester_count), function ($semester_no) use ($semester, $lastTwo) {
        if (in_array($semester_no, $lastTwo, true)) {
            return true;
        }
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
 * 3+1 ve 7+1 staj sistemlerinde döneme göre önerilen yarıyıl numarasını döner.
 * Bahar döneminde staj dersleri çift yarıyıla (3 -> 4, 7 -> 8),
 * Güz döneminde staj dersleri tek yarıyıla (4 -> 3, 8 -> 7) aktarılmış olarak listelenir.
 *
 * @param int $semesterNo Mevcut yarıyıl numarası
 * @param string $semester Dönem ('Güz' veya 'Bahar')
 * @param int $totalSemesters Programın toplam yarıyıl sayısı (MYO: 4, Fakülte: 8)
 * @return int
 */
function getSuggestedSemesterNo(int $semesterNo, string $semester, int $totalSemesters = 4): int
{
    if ($semester === 'Bahar') {
        if ($totalSemesters <= 4 && $semesterNo === 3) {
            return 4;
        }
        if ($totalSemesters >= 8 && $semesterNo === 7) {
            return 8;
        }
    } elseif ($semester === 'Güz') {
        if ($totalSemesters <= 4 && $semesterNo === 4) {
            return 3;
        }
        if ($totalSemesters >= 8 && $semesterNo === 8) {
            return 7;
        }
    }
    return $semesterNo;
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
        if (!empty($lessonMax) && (int) $lessonMax > 0) {
            return (int) $lessonMax;
        }

        // Programda ders yoksa bağlı olduğu bölüm üzerinden bak
        if (empty($departmentId)) {
            $program = (new Program())->find($programId);
            if ($program && $program->department_id) {
                $departmentId = (int) $program->department_id;
            }
        }
    }

    // 2. Bölüm ID verilmişse: Bölüm derslerinin maksimum semester_no değeri
    if (!empty($departmentId)) {
        $lessonMax = (new Lesson())->get()->where(['department_id' => $departmentId])->max('semester_no');
        if (!empty($lessonMax) && (int) $lessonMax > 0) {
            return (int) $lessonMax;
        }

        // Bölümde ders yoksa bağlı olduğu birim üzerinden bak
        if (empty($unitId)) {
            $department = (new Department())->find($departmentId);
            if ($department && $department->unit_id) {
                $unitId = (int) $department->unit_id;
            }
        }
    }

    // 3. Birim ID verilmişse: Birime ait bölümlerdeki derslerin maksimum semester_no değeri
    if (!empty($unitId)) {
        $departments = (new Department())->get()->where(['unit_id' => $unitId])->all();
        $deptIds = array_column($departments, 'id');
        if (!empty($deptIds)) {
            $lessonMax = (new Lesson())->get()->where(['department_id' => ['in' => $deptIds]])->max('semester_no');
            if (!empty($lessonMax) && (int) $lessonMax > 0) {
                return (int) $lessonMax;
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
    return (!empty($maxInDb) && (int) $maxInDb > 0) ? (int) $maxInDb : 8;
}

/**
 * Yarıyıl/Sınıf seçimi için uygun dönem listesini döner.
 *
 * @param string|null $semester Güz, Bahar, Yaz veya null
 * @param int|null $maxSemester Maksimum yarıyıl sayısı (varsayılan 12)
 * @param bool $includeInternshipSemesters 3+1 / 7+1 gibi staj sistemlerinde son iki yarıyılı her iki döneme de dahil et
 * @return array<int, string> [semester_no => 'X. Sınıf (Y. Yarıyıl)']
 */
function getSemesterSelectOptions(?string $semester = null, ?int $maxSemester = null, bool $includeInternshipSemesters = false): array
{
    $semester = $semester ?? getSettingValue('semester') ?? 'Güz';
    $semesters = getSemesterNumbers($semester, $maxSemester ?? 12, $includeInternshipSemesters);
    $options = [];

    foreach ($semesters as $i) {
        $classNo = getClassFromSemesterNo($i);
        $options[$i] = "{$classNo}. Sınıf ({$i}. Yarıyıl)";
    }

    return $options;
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
