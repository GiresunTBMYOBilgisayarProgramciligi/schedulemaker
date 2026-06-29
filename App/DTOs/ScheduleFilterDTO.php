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
        public ?int $owner_id = null,
        // Export options
        public ?bool $show_code = null,
        public ?bool $show_lecturer = null,
        public ?bool $show_program = null,
        public ?bool $show_observer = null
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
            owner_id: isset($data['owner_id']) && $data['owner_id'] !== '' ? (int)$data['owner_id'] : null,
            show_code: isset($data['show_code']) ? (bool)$data['show_code'] : null,
            show_lecturer: isset($data['show_lecturer']) ? (bool)$data['show_lecturer'] : null,
            show_program: isset($data['show_program']) ? (bool)$data['show_program'] : null,
            show_observer: isset($data['show_observer']) ? (bool)$data['show_observer'] : null
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
            'show_code' => $this->show_code,
            'show_lecturer' => $this->show_lecturer,
            'show_program' => $this->show_program,
            'show_observer' => $this->show_observer,
        ], fn($value) => $value !== null);
    }
}
