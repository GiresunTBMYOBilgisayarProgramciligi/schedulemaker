<?php

namespace App\DTOs;

use function App\Helpers\getSettingValue;

class CombineExamLessonDTO
{
    public int $parentId;
    public int $childId;
    public string $semester;
    public string $academicYear;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->parentId = (int) ($data['parent_lesson_id'] ?? 0);
        $dto->childId = (int) ($data['child_lesson_id'] ?? 0);
        $dto->semester = $data['semester'] ?? getSettingValue('semester');
        $dto->academicYear = $data['academic_year'] ?? getSettingValue('academic_year');
        return $dto;
    }
}
