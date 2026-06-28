<?php

namespace App\Validators\Schedule;

/**
 * Schedule çakışma kontrol işlemleri için filtre doğrulayıcı
 * 
 * Ders/sınav ekleme öncesinde yapılan çakışma kontrolü operasyonlarının
 * filtrelerini doğrular.
 * 
 * Desteklenen operasyonlar:
 * - checkScheduleCrash: Çakışma kontrolü (items JSON)
 */
class ScheduleConflictFilterValidator extends BaseScheduleFilterValidator
{
    protected function getOperationRules(): array
    {
        return [
            'checkScheduleCrash' => [
                'required' => ['items'],
                'optional' => [],
                'defaults' => [],
            ],
        ];
    }
}
