<?php

namespace App\DTOs;

use function App\Helpers\getSettingValue;

/**
 * Sınav birleştirme verisi için kesin tipli DTO.
 */
readonly class CombineExamLessonDTO
{
    public function __construct(
        public int $parentId,
        public int $childId,
        public string $semester,
        public string $academicYear
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            parentId: (int) ($data['parent_lesson_id'] ?? 0),
            childId: (int) ($data['child_lesson_id'] ?? 0),
            semester: (string) ($data['semester'] ?? getSettingValue('semester')),
            academicYear: (string) ($data['academic_year'] ?? getSettingValue('academic_year'))
        );
    }

    public function toArray(): array
    {
        return [
            'parent_lesson_id' => $this->parentId,
            'child_lesson_id'  => $this->childId,
            'semester'         => $this->semester,
            'academic_year'    => $this->academicYear,
        ];
    }
}
