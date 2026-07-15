<?php

namespace App\DTOs;

use function App\Helpers\getSettingValue;

class DeleteCombineLessonDTO
{
    public int $id;
    public string $type; // 'lesson' or 'exam'
    public string $semester;
    public string $academicYear;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->id = (int) ($data['id'] ?? 0);
        $dto->type = $data['type'] ?? 'lesson';
        $dto->semester = $data['semester'] ?? getSettingValue('semester');
        $dto->academicYear = $data['academic_year'] ?? getSettingValue('academic_year');
        return $dto;
    }
}
