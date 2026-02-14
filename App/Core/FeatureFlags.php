<?php

namespace App\Core;

use function App\Helpers\getSettingValue;

/**
 * Feature Flag yönetimi
 * 
 * Test ortamında yeni özellikleri açıp kapatmak için kullanılır.
 * Production'da hata olursa anında eski sisteme dönüş sağlar.
 * 
 * Kullanım: Settings tablosunda (`key`, `group`) ile flag tutulur
 * - key: 'use_new_schedule_service'  
 * - group: 'feature_flags'
 * - value: '1' (aktif) / '0' (pasif)
 */
class FeatureFlags
{
    /**
     * Yeni Schedule Service kullanılsın mı?
     * Settings: key='use_new_schedule_service', group='feature_flags'
     * @return bool
     */
    public static function useNewScheduleService(): bool
    {
        return self::isEnabled('use_new_schedule_service', 'feature_flags');
    }

    /**
     * Yeni Lesson Service kullanılsın mı?
     * Settings: key='use_new_lesson_service', group='feature_flags'
     * @return bool
     */
    public static function useNewLessonService(): bool
    {
        return self::isEnabled('use_new_lesson_service', 'feature_flags');
    }

    /**
     * Feature flag kontrolü
     * @param string $flagKey Flag key (settings.key)
     * @param string $flagGroup Flag group (settings.group)
     * @return bool
     */
    private static function isEnabled(string $flagKey, string $flagGroup = 'feature_flags'): bool
    {
        $value = getSettingValue($flagKey, $flagGroup);
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
