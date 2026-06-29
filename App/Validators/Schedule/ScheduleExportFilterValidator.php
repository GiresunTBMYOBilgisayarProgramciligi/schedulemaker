<?php

namespace App\Validators\Schedule;

use App\DTOs\ScheduleFilterDTO;

/**
 * Schedule dışa aktarım (export) işlemleri için filtre doğrulayıcı
 * 
 * Excel, ICS ve diğer dışa aktarım formatlarının filtrelerini doğrular.
 * 
 * Desteklenen operasyonlar:
 * - exportScheduleAction: AjaxRouter export tetiklemesi
 * - exportSchedule: Doğrudan export çağrısı
 * - generateScheduleFilters: ScheduleExportFilterBuilder filtre üretimi
 * - exportScheduleIcsAction: ICS formatı export
 */
class ScheduleExportFilterValidator extends BaseScheduleFilterValidator
{
    protected function getOperationRules(): array
    {
        return [
            'exportScheduleAction' => [
                'required' => ['type', 'owner_type'],
                'optional' => ['owner_id', 'semester_no', 'show_code', 'show_lecturer', 'show_program', 'show_observer'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'generateScheduleFilters' => [
                'required' => ['type', 'owner_type'],
                'optional' => ['owner_id', 'semester_no', 'show_code', 'show_lecturer', 'show_program', 'show_observer'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'exportSchedule' => [
                'required' => ['type', 'owner_type'],
                'optional' => ['owner_id', 'semester_no', 'show_code', 'show_lecturer', 'show_program', 'show_observer'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'exportScheduleIcsAction' => [
                'required' => ['owner_type'],
                'optional' => ['semester_no', 'owner_id'],
                'defaults' => ['semester', 'academic_year', 'type'],
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
