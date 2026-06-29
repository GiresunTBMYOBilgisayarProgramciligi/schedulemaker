<?php

namespace App\DTOs;

/**
 * Ders/Sınav programı dışa aktarma (export) işlemlerinde kullanılan filtreleri temsil eder.
 */
readonly class ScheduleExportFilterDTO extends ScheduleFilterDTO
{
    public function __construct(
        string $type,
        ?string $semester = null,
        ?string $academic_year = null,
        ?int $semester_no = null,
        ?string $owner_type = null,
        ?int $owner_id = null,
        public ?bool $show_code = null,
        public ?bool $show_lecturer = null,
        public ?bool $show_program = null,
        public ?bool $show_observer = null
    ) {
        parent::__construct($type, $semester, $academic_year, $semester_no, $owner_type, $owner_id);
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
        return array_merge(parent::toArray(), array_filter([
            'show_code' => $this->show_code,
            'show_lecturer' => $this->show_lecturer,
            'show_program' => $this->show_program,
            'show_observer' => $this->show_observer,
        ], fn($value) => $value !== null));
    }
}
