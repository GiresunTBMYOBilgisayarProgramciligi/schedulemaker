<?php

namespace App\DTOs;

use function App\Helpers\formatLessonName;

/**
 * Ders verisi için kesin tipli DTO.
 */
readonly class LessonDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $code = null,
        public ?int $group_no = null,
        public ?string $name = null,
        public ?int $size = null,
        public ?int $hours = null,
        public ?int $type = null,
        public ?int $semester_no = null,
        public ?int $lecturer_id = null,
        public ?int $department_id = null,
        public ?int $program_id = null,
        public ?string $semester = null,
        public ?int $classroom_type = null,
        public ?string $academic_year = null,
        public ?int $building_id = null,
        public bool $unassign_lecturer = false
    ) {
    }

    public static function fromArray(array $data): self
    {
        $hasLecturerKey = array_key_exists('lecturer_id', $data);
        $rawLecturer = $data['lecturer_id'] ?? null;
        $lecturerId = ($hasLecturerKey && $rawLecturer !== '' && $rawLecturer !== null && (int)$rawLecturer !== 0)
            ? (int)$rawLecturer
            : null;
        $unassignLecturer = $hasLecturerKey && ($rawLecturer === '' || $rawLecturer === null || (int)$rawLecturer === 0);

        return new self(
            id: isset($data['id']) && $data['id'] !== '' && (int)$data['id'] > 0 ? (int)$data['id'] : null,
            code: $data['code'] ?? null,
            group_no: isset($data['group_no']) && $data['group_no'] !== '' ? (int)$data['group_no'] : null,
            name: isset($data['name']) ? formatLessonName($data['name']) : null,
            size: isset($data['size']) && $data['size'] !== '' ? (int)$data['size'] : null,
            hours: isset($data['hours']) && $data['hours'] !== '' ? (int)$data['hours'] : null,
            type: isset($data['type']) && $data['type'] !== '' ? (int)$data['type'] : null,
            semester_no: isset($data['semester_no']) && $data['semester_no'] !== '' ? (int)$data['semester_no'] : null,
            lecturer_id: $lecturerId,
            department_id: isset($data['department_id']) && $data['department_id'] !== '' && $data['department_id'] != '0' ? (int)$data['department_id'] : null,
            program_id: isset($data['program_id']) && $data['program_id'] !== '' && $data['program_id'] != '0' ? (int)$data['program_id'] : null,
            semester: $data['semester'] ?? null,
            classroom_type: isset($data['classroom_type']) && $data['classroom_type'] !== '' ? (int)$data['classroom_type'] : null,
            academic_year: $data['academic_year'] ?? null,
            building_id: isset($data['building_id']) && $data['building_id'] !== '' && $data['building_id'] != '0' ? (int)$data['building_id'] : null,
            unassign_lecturer: $unassignLecturer
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'code'           => $this->code,
            'group_no'       => $this->group_no,
            'name'           => $this->name,
            'size'           => $this->size,
            'hours'          => $this->hours,
            'type'           => $this->type,
            'semester_no'    => $this->semester_no,
            'lecturer_id'    => $this->lecturer_id,
            'department_id'  => $this->department_id,
            'program_id'     => $this->program_id,
            'semester'       => $this->semester,
            'classroom_type' => $this->classroom_type,
            'academic_year'  => $this->academic_year,
            'building_id'    => $this->building_id,
        ], fn($value) => $value !== null);
    }
}
