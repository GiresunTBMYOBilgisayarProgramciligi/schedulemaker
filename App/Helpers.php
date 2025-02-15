<?php

namespace App\Helpers;

use App\Controllers\LessonController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
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
 * @param string|null $semester
 * @return array
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
                'Güz' => $semester_no % 2 === 1, // Tek sayılar
                'Bahar' => $semester_no % 2 === 0, // Çift sayılar
                default => true, // Varsayılan: Tüm dönemleri döndür
            };
        }));
    } catch (Exception $e) {
        error_log("getSemesterNumbers Error: " . $e->getMessage());
        $_SESSION['errors'][] = "getSemesterNumbers Error: " . $e->getMessage();
        return [];
    }
}

/**
 * işlemlerin yapılıp yapılamayacağına dair kontrolü yapan fonksiyon.
 * Eğer işlem için gerekli yetki seviyesi kullanıcının yetki seviyesinden küçükse kullanıcı işlemi yapmaya yetkilidir.
 * @param string $role "admin","manager", "submanager", "department_head", "lecturer", "user"
 * @param bool $reverse eğer true girilmişse belirtilen rolden düşük roller için yetki verir
 * @return bool
 */
function isAuthorized(string $role, $reverse = false): bool
{
    try {
        $roleLevels = [
            "admin" => 10,
            "manager" => 9,
            "submanager" => 8,
            "department_head" => 7,
            "lecturer" => 6,
            "user" => 5
        ];
        return (new UserController())->canUserDoAction($roleLevels[$role],$reverse);
    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        return false;
    }
}