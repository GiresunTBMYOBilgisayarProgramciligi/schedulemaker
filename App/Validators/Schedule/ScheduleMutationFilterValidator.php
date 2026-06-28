<?php

namespace App\Validators\Schedule;

/**
 * Schedule yazma (silme/tercih kaydetme) işlemleri için filtre doğrulayıcı
 * 
 * Schedule silme ve tercih kaydetme gibi mutasyon operasyonlarının
 * filtrelerini doğrular.
 * 
 * Desteklenen operasyonlar:
 * - deleteScheduleAction: AjaxRouter üzerinden silme
 * - deleteSchedule: Doğrudan silme çağrısı
 * - checkAndDeleteSchedule: Kontrollü silme
 * - saveSchedulePreferenceAction: Hoca tercih kaydı
 */
class ScheduleMutationFilterValidator extends BaseScheduleFilterValidator
{
    protected function getOperationRules(): array
    {
        return [
            'deleteScheduleAction' => [
                'required' => ['type', 'time'],
                'optional' => ['owner_type', 'owner_id', 'lesson_id', 'classroom_id', 'lecturer_id', 'day_index'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'deleteSchedule' => [
                'required' => ['type', 'time', 'owner_type', 'owner_id'],
                'optional' => ['semester_no', 'day', 'day_index', 'classroom_id'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'checkAndDeleteSchedule' => [
                'required' => ['day_index'],
                'optional' => ['day'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'saveSchedulePreferenceAction' => [
                'required' => ['type', 'owner_type', 'owner_id', 'time', 'day_index', 'day'],
                'optional' => ['semester_no'],
                'defaults' => ['semester', 'academic_year'],
            ],
        ];
    }
}
