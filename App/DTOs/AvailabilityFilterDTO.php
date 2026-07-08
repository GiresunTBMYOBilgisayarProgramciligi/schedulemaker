<?php

namespace App\DTOs;

/**
 * Müsaitlik (Derslik, Gözetmen, Hoca, Program) kontrollerinde kullanılan filtreleri temsil eder.
 */
readonly class AvailabilityFilterDTO
{
    public function __construct(
        public int $day_index,
        public string $start_time,
        public string $end_time,
        public string $type,
        public ?string $semester = null,
        public ?string $academic_year = null,
        public ?int $week_index = 0,
        // Gözetmen ve Derslik aramasında kullanılır
        public ?int $lesson_id = null,
        public ?int $schedule_id = null,
        public mixed $items = null,
        public ?int $exam_duration = null, // Sınav süresi
        public ?string $classroom_type = null // İstenen derslik tipi
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            day_index: (int)($data['day_index'] ?? 0),
            start_time: $data['start_time'] ?? '',
            end_time: $data['end_time'] ?? '',
            type: $data['type'] ?? 'lesson',
            semester: $data['semester'] ?? null,
            academic_year: $data['academic_year'] ?? null,
            week_index: isset($data['week_index']) ? (int)$data['week_index'] : 0,
            lesson_id: isset($data['lesson_id']) ? (int)$data['lesson_id'] : null,
            schedule_id: isset($data['schedule_id']) ? (int)$data['schedule_id'] : null,
            items: $data['items'] ?? null,
            exam_duration: isset($data['exam_duration']) ? (int)$data['exam_duration'] : null,
            classroom_type: $data['classroom_type'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'day_index' => $this->day_index,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'type' => $this->type,
            'semester' => $this->semester,
            'academic_year' => $this->academic_year,
            'week_index' => $this->week_index,
            'lesson_id' => $this->lesson_id,
            'schedule_id' => $this->schedule_id,
            'items' => $this->items,
            'exam_duration' => $this->exam_duration,
            'classroom_type' => $this->classroom_type,
        ], fn($value) => $value !== null);
    }
}
