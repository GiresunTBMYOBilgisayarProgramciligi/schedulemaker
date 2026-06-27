<?php

namespace App\DTOs;

use App\Enums\ClassroomType;

class ClassroomDTO
{
    public ?string $name = null;
    public ?int $class_size = null;
    public ?int $exam_size = null;
    public ?ClassroomType $type = null;

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->name = $data['name'] ?? null;
        $dto->class_size = isset($data['class_size']) ? (int)$data['class_size'] : null;
        $dto->exam_size = isset($data['exam_size']) ? (int)$data['exam_size'] : null;
        
        if (isset($data['type'])) {
            $dto->type = ClassroomType::tryFrom((int)$data['type']);
        }
        
        return $dto;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class_size' => $this->class_size,
            'exam_size' => $this->exam_size,
            'type' => $this->type?->value,
        ];
    }
}
