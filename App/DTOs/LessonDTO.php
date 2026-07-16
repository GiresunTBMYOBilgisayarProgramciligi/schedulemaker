<?php

namespace App\DTOs;

use function App\Helpers\formatLessonName;

class LessonDTO
{
    public ?string $code;
    public ?int $group_no;
    public ?string $name;
    public ?int $size;
    public ?int $hours;
    public ?int $type;
    public ?int $semester_no;
    public ?int $lecturer_id;
    public ?int $department_id;
    public ?int $program_id;
    public ?string $semester;
    public ?int $classroom_type;
    public ?string $academic_year;
    public ?int $building_id;

    public function __construct(
        ?string $code,
        ?int $group_no,
        ?string $name,
        ?int $size,
        ?int $hours,
        ?int $type,
        ?int $semester_no,
        ?int $lecturer_id,
        ?int $department_id,
        ?int $program_id,
        ?string $semester,
        ?int $classroom_type,
        ?string $academic_year,
        ?int $building_id
    ) {
        $this->code = $code;
        $this->group_no = $group_no;
        $this->name = $name;
        $this->size = $size;
        $this->hours = $hours;
        $this->type = $type;
        $this->semester_no = $semester_no;
        $this->lecturer_id = $lecturer_id;
        $this->department_id = $department_id;
        $this->program_id = $program_id;
        $this->semester = $semester;
        $this->classroom_type = $classroom_type;
        $this->academic_year = $academic_year;
        $this->building_id = $building_id;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['code'] ?? null,
            isset($data['group_no']) && $data['group_no'] !== '' ? (int)$data['group_no'] : null,
            isset($data['name']) ? formatLessonName($data['name']) : null,
            isset($data['size']) && $data['size'] !== '' ? (int)$data['size'] : null,
            isset($data['hours']) && $data['hours'] !== '' ? (int)$data['hours'] : null,
            isset($data['type']) && $data['type'] !== '' ? (int)$data['type'] : null,
            isset($data['semester_no']) && $data['semester_no'] !== '' ? (int)$data['semester_no'] : null,
            isset($data['lecturer_id']) && $data['lecturer_id'] !== '' && $data['lecturer_id'] != '0' ? (int)$data['lecturer_id'] : null,
            isset($data['department_id']) && $data['department_id'] !== '' && $data['department_id'] != '0' ? (int)$data['department_id'] : null,
            isset($data['program_id']) && $data['program_id'] !== '' && $data['program_id'] != '0' ? (int)$data['program_id'] : null,
            $data['semester'] ?? null,
            isset($data['classroom_type']) && $data['classroom_type'] !== '' ? (int)$data['classroom_type'] : null,
            $data['academic_year'] ?? null,
            isset($data['building_id']) && $data['building_id'] !== '' && $data['building_id'] != '0' ? (int)$data['building_id'] : null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code,
            'group_no' => $this->group_no,
            'name' => $this->name,
            'size' => $this->size,
            'hours' => $this->hours,
            'type' => $this->type,
            'semester_no' => $this->semester_no,
            'lecturer_id' => $this->lecturer_id,
            'department_id' => $this->department_id,
            'program_id' => $this->program_id,
            'semester' => $this->semester,
            'classroom_type' => $this->classroom_type,
            'academic_year' => $this->academic_year,
            'building_id' => $this->building_id
        ], fn($value) => $value !== null);
    }
}
