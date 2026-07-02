<?php

namespace App\Validators\Schedule;

use App\DTOs\AvailabilityFilterDTO;

/**
 * Schedule müsaitlik kontrol işlemleri için filtre doğrulayıcı
 * 
 * Uygun derslik, gözetmen ve program/hoca/derslik çakışma haritası
 * sorgulama operasyonlarının filtrelerini doğrular.
 * 
 * Desteklenen operasyonlar:
 * - availableClassrooms: Uygun derslik sorgulama
 * - availableObservers: Uygun gözetmen sorgulama
 * - checkLecturerScheduleAction: Hoca çakışma haritası
 * - checkClassroomScheduleAction: Derslik çakışma haritası
 * - checkProgramScheduleAction: Program çakışma haritası
 */
class ScheduleAvailabilityFilterValidator extends BaseScheduleFilterValidator
{
    protected function getOperationRules(): array
    {
        return [
            'availableClassrooms' => [
                'required' => ['schedule_id', 'items', 'lesson_id', 'day_index', 'week_index'],
                'optional' => ['hours', 'startTime'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'availableObservers' => [
                'required' => ['type', 'items', 'day_index', 'week_index'],
                'optional' => ['hours', 'startTime'],
                'defaults' => ['semester', 'academic_year'],
            ],
            'checkLecturerScheduleAction' => [
                'required' => ['type', 'lesson_id'],
                'optional' => ['week_index'],
                'defaults' => ['semester', 'academic_year', 'week_index'],
            ],
            'checkClassroomScheduleAction' => [
                'required' => ['type', 'lesson_id'],
                'optional' => ['week_index'],
                'defaults' => ['semester', 'academic_year', 'week_index'],
            ],
            'checkProgramScheduleAction' => [
                'required' => ['type', 'lesson_id'],
                'optional' => ['week_index'],
                'defaults' => ['semester', 'academic_year', 'week_index'],
            ],
        ];
    }

    /**
     * Filtreleri sanitize edip AvailabilityFilterDTO olarak döner
     */
    public function getDTO(array $data, string $operation = 'availableClassrooms'): AvailabilityFilterDTO
    {
        // availableClassrooms vb işlemlerde array formatı doğrudan DTO'ya map edilemeyebilir.
        // Ama şimdilik fromArray kullanıp sonrasında özelleştirebiliriz.
        $sanitized = $this->sanitize($data, $operation);
        return AvailabilityFilterDTO::fromArray($sanitized);
    }
}
