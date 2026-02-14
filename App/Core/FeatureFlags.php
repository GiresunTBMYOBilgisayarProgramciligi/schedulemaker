<?php

namespace App\Core;

use function App\Helpers\getSettingValue;

/**
 * Feature Flag yönetimi
 * 
 * Test ortamında yeni özellikleri açıp kapatmak için kullanılır.
 * Production'da hata olursa anında eski sisteme dönüş sağlar.
 * 
 * Kullanım: Settings tablosunda flag değerini '1' yap (aktif) veya '0' (pasif)
 */
class FeatureFlags
{
    /**
     * Yeni Schedule Service kullanılsın mı?
     * Setting: use_new_schedule_service = '1' (aktif) / '0' (pasif)
     * @return bool
     */
    public static function useNewScheduleService(): bool
    {
        return self::isEnabled('use_new_schedule_service');
    }

    /**
     * Yeni Lesson Service kullanılsın mı?
     * Setting: use_new_lesson_service = '1' (aktif) / '0' (pasif)
     * @return bool
     */
    public static function useNewLessonService(): bool
    {
        return self::isEnabled('use_new_lesson_service');
    }

    /**
     * Feature flag kontrolü
     * @param string $flagName
     * @return bool
     */
    private static function isEnabled(string $flagName): bool
    {
        $value = getSettingValue($flagName);
        return $value === '1' || $value === 'true' || $value === true;
    }

    /**
     * Tüm feature flag'lerin durumunu döner
     * @return array
     */
    public static function getAll(): array
    {
        return [
            'use_new_schedule_service' => self::useNewScheduleService(),
            'use_new_lesson_service' => self::useNewLessonService()
        ];
    }
}
