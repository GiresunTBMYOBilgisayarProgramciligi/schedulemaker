<?php

namespace App\Validators\Schedule;

use App\DTOs\ConflictFilterDTO;

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

    /**
     * Filtreleri sanitize edip ConflictFilterDTO olarak döner
     */
    public function getDTO(array $data, string $operation = 'checkScheduleCrash'): ConflictFilterDTO
    {
        $sanitized = $this->sanitize($data, $operation);
        // checkScheduleCrash'e gelen data içerisinde raw JS verileri var,
        // DTO'ya uygun hale getirmek gerekebilir. Şimdilik fromArray
        return ConflictFilterDTO::fromArray($sanitized);
    }
}
