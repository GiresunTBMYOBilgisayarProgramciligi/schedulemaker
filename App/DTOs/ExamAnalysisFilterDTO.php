<?php

namespace App\DTOs;

use App\Enums\ExamType;

/**
 * Sınav analizleri sayfasının filtrelerini temsil eder.
 */
readonly class ExamAnalysisFilterDTO
{
    public function __construct(
        public string $semester,
        public string $academic_year,
        public string $exam_type = ExamType::MIDTERM->value,
        public ?int $unit_id = null,
        public ?int $department_id = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            semester: (string) $data['semester'],
            academic_year: (string) $data['academic_year'],
            exam_type: (string) ($data['exam_type'] ?? ExamType::MIDTERM->value),
            unit_id: isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null,
            department_id: isset($data['department_id']) && $data['department_id'] !== '' ? (int) $data['department_id'] : null
        );
    }

    /**
     * Birim ve bölüm filtresi değiştirilmiş kopyasını döner (örn. kullanıcının yetkisiyle sınırlanmış hali).
     */
    public function withScope(?int $unitId, ?int $departmentId): self
    {
        return new self($this->semester, $this->academic_year, $this->exam_type, $unitId, $departmentId);
    }

    public function examType(): ExamType
    {
        return ExamType::from($this->exam_type);
    }

    public function toArray(): array
    {
        return [
            'semester' => $this->semester,
            'academic_year' => $this->academic_year,
            'exam_type' => $this->exam_type,
            'unit_id' => $this->unit_id,
            'department_id' => $this->department_id,
        ];
    }
}
