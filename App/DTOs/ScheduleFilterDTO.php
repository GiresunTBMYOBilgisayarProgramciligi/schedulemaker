<?php

namespace App\DTOs;

/**
 * Ders/Sınav programı çekme veya dışa aktarma işlemlerinde kullanılan filtreleri temsil eder.
 */
readonly class ScheduleFilterDTO
{
    public function __construct(
        public string $type,
        public ?string $semester = null,
        public ?string $academic_year = null,
        public ?int $semester_no = null,
        public ?string $owner_type = null,
        public ?int $owner_id = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'lesson',
            semester: $data['semester'] ?? null,
            academic_year: $data['academic_year'] ?? null,
            semester_no: isset($data['semester_no']) && $data['semester_no'] !== '' ? (int)$data['semester_no'] : null,
            owner_type: $data['owner_type'] ?? null,
            owner_id: isset($data['owner_id']) && $data['owner_id'] !== '' ? (int)$data['owner_id'] : null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'semester' => $this->semester,
            'academic_year' => $this->academic_year,
            'semester_no' => $this->semester_no,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
        ], fn($value) => $value !== null);
    }
}
