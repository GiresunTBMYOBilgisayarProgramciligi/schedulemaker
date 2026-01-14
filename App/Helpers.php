<?php
//todo App/Helpers içerisine taşınabilir.
namespace App\Helpers;

use App\Controllers\LessonController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use Exception;

/**
 * @param null $default İstenen ayar bulunamazsa dönülecek ön tanımlı değer
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
 * işlemlerin yapılıp yapılamayacağına dair kontrolü yapan fonksiyon.
 * Eğer işlem için gerekli yetki seviyesi kullanıcının yetki seviyesinden küçükse kullanıcı işlemi yapmaya yetkilidir.
 * @param string $role "admin","manager", "submanager", "department_head", "lecturer", "user"
 * @param bool $reverse eğer true girilmişse belirtilen rolden düşük roller için yetki verir
 * @param null $model Denetim yapılan model
 * @return bool
 * @throws Exception
 */
function isAuthorized(string $role, bool $reverse = false, $model = null): bool
{
    $roleLevels = [
        "admin" => 10,
        "manager" => 9,
        "submanager" => 8,
        "department_head" => 7,
        "lecturer" => 6,
        "user" => 5
    ];
    if (!$roleLevels[$role])
        throw new Exception("Yetkilendirme işlemi için doğru bir yetki belirtilmemiş");
    return (new UserController())->canUserDoAction($roleLevels[$role], $reverse, $model);

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
