<?php

namespace App\DTOs;

/**
 * Akademisyen tarafından oluşturulan/güncellenen program notunu taşıyan DTO
 */
readonly class ScheduleNoteDTO
{
    public function __construct(
        public int $userId,
        public string $academicYear,
        public string $semester,
        public string $scheduleType,
        public string $note,
        public ?int $id = null
    ) {
    }

    public static function fromArray(array $validatedData): self
    {
        return new self(
            userId: (int)$validatedData['user_id'],
            academicYear: $validatedData['academic_year'],
            semester: $validatedData['semester'],
            scheduleType: $validatedData['schedule_type'],
            note: $validatedData['note'],
            id: !empty($validatedData['id']) ? (int)$validatedData['id'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'academic_year' => $this->academicYear,
            'semester' => $this->semester,
            'schedule_type' => $this->scheduleType,
            'note' => $this->note,
        ];
    }
}
