<?php

namespace App\DTOs;

/**
 * Çakışma kontrollerinde kullanılan DTO.
 */
readonly class ConflictFilterDTO
{
    public function __construct(
        public int $day_index,
        public int $week_index,
        public string $start_time,
        public string $end_time,
        public string $type, // 'lesson' or 'exam' or specific exam type
        public array $assignments, // [['owner_type' => 'user', 'owner_id' => 1], ...]
        public ?string $semester = null,
        public ?string $academic_year = null,
        public ?int $ignore_item_id = null // Güncelleme sırasında kendisini yoksaymak için
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            day_index: (int)($data['day_index'] ?? 0),
            week_index: (int)($data['week_index'] ?? 0),
            start_time: $data['start_time'] ?? '',
            end_time: $data['end_time'] ?? '',
            type: $data['type'] ?? 'lesson',
            assignments: is_array($data['assignments'] ?? null) ? $data['assignments'] : [],
            semester: $data['semester'] ?? null,
            academic_year: $data['academic_year'] ?? null,
            ignore_item_id: isset($data['ignore_item_id']) ? (int)$data['ignore_item_id'] : null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'day_index' => $this->day_index,
            'week_index' => $this->week_index,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'type' => $this->type,
            'assignments' => $this->assignments,
            'semester' => $this->semester,
            'academic_year' => $this->academic_year,
            'ignore_item_id' => $this->ignore_item_id
        ], fn($value) => $value !== null);
    }
}
