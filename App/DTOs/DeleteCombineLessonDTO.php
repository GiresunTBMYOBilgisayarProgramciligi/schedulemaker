<?php

namespace App\DTOs;

use function App\Helpers\getSettingValue;

/**
 * Birleştirilmiş ders/sınav bağlantısını silme verisi için kesin tipli DTO.
 */
readonly class DeleteCombineLessonDTO
{
    public function __construct(
        public int $id,
        public string $type = 'lesson', // 'lesson' or 'exam'
        public string $semester = '',
        public string $academicYear = ''
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            type: (string) ($data['type'] ?? 'lesson'),
            semester: (string) ($data['semester'] ?? getSettingValue('semester')),
            academicYear: (string) ($data['academic_year'] ?? getSettingValue('academic_year'))
        );
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'semester'      => $this->semester,
            'academic_year' => $this->academicYear,
        ];
    }
}
