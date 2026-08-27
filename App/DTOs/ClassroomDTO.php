<?php

namespace App\DTOs;

use App\Enums\ClassroomType;

/**
 * Derslik verisi için kesin tipli DTO.
 */
readonly class ClassroomDTO
{
    public function __construct(
        public ?string $name = null,
        public ?int $class_size = null,
        public ?int $exam_size = null,
        public ?int $building_id = null,
        public ?ClassroomType $type = null
    ) {
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $type = null;
        if (isset($data['type'])) {
            $type = $data['type'] instanceof ClassroomType
                ? $data['type']
                : ClassroomType::tryFrom((int)$data['type']);
        }

        return new self(
            name: $data['name'] ?? null,
            class_size: isset($data['class_size']) && $data['class_size'] !== '' ? (int)$data['class_size'] : null,
            exam_size: isset($data['exam_size']) && $data['exam_size'] !== '' ? (int)$data['exam_size'] : null,
            building_id: isset($data['building_id']) && $data['building_id'] !== '' ? (int)$data['building_id'] : null,
            type: $type
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'class_size'  => $this->class_size,
            'exam_size'   => $this->exam_size,
            'building_id' => $this->building_id,
            'type'        => $this->type?->value,
        ];
    }
}
