<?php

namespace App\Helpers;

use App\Controllers\LessonController;
use App\Controllers\SettingsController;
use Exception;

function getSetting($key = null, $group = "general")
{
    try {
        $settingsController = new SettingsController();
        $setting = $settingsController->getSetting($key, $group);
        return match ($setting->type) {
            'integer' => (int)$setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            default => $setting->value
        };
    } catch (Exception $e) {
        $_SESSION['errors'][] = "getSetting hatası";
        $_SESSION['errors'][] = $e->getMessage();
        return false;
    }
}

/**
 * @return string
 */
function getCurrentSemester()
{
    try {
        return getSetting('academic_year') . " " . getSetting('semester');
    } catch (Exception $e) {
        $_SESSION['errors'][] = "Semester/Dönem Bilgisi oluşturulurken hata oluştu";
        return false;
    }
}

/**
 * @return array
 * @throws Exception
 */
function getSemesterNumbers(?string $semester = null): array
{
    try {
        // Eğer parametre verilmemişse ayarlar tablosundan al
        $semester = $semester ?? getSetting('semester');

        // Geçerli dönem sayısını al
        $semester_count = (new LessonController())->getSemesterCount();

        // Güz döneminde **tek**, Bahar döneminde **çift** sayılar seçilmeli
        return array_values(array_filter(range(1, $semester_count), function ($semester_no) use ($semester) {
            return match ($semester) {
                'Güz'   => $semester_no % 2 === 1, // Tek sayılar
                'Bahar' => $semester_no % 2 === 0, // Çift sayılar
                default => true, // Varsayılan: Tüm dönemleri döndür
            };
        }));
    } catch (Exception $e) {
        error_log("getSemesterNumbers Error: " . $e->getMessage());
        return [];
    }
}