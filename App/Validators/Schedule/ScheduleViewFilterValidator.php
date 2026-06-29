<?php

namespace App\Validators\Schedule;

use App\DTOs\ScheduleFilterDTO;

/**
 * Schedule görüntüleme işlemleri için filtre doğrulayıcı
 * 
 * Schedule tablosunu görüntüleme, kart hazırlama ve uygun ders listeleme
 * gibi okuma (read) operasyonlarının filtrelerini doğrular.
 * 
 * Desteklenen operasyonlar:
 * - getSchedulesHTML: HTML schedule tablosu oluşturma
 * - prepareScheduleCard: Schedule kartı hazırlama
 * - prepareScheduleRows: Schedule satırları hazırlama
 * - availableLessons: Uygun ders listeleme
 */
class ScheduleViewFilterValidator extends BaseScheduleFilterValidator
{
    protected function getOperationRules(): array
    {
        return [
            'getSchedulesHTML' => [
                'required' => ['type', 'owner_type', 'owner_id'],
                'optional' => ['semester_no'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'prepareScheduleCard' => [
                'required' => ['type', 'owner_type', 'owner_id'],
                'optional' => ['semester_no'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'prepareScheduleRows' => [
                'required' => ['type', 'owner_type', 'owner_id', 'semester_no'],
                'optional' => [],
                'defaults' => ['semester', 'academic_year'],
            ],
            'availableLessons' => [
                'required' => ['type', 'owner_type', 'owner_id', 'semester_no'],
                'optional' => [],
                'defaults' => ['semester', 'academic_year'],
            ],
        ];
    }

    /**
     * Filtreleri sanitize edip ScheduleFilterDTO olarak döner
     */
    public function getDTO(array $data, string $operation): ScheduleFilterDTO
    {
        $sanitized = $this->sanitize($data, $operation);
        return ScheduleFilterDTO::fromArray($sanitized);
    }
}
