<?php

namespace App\DTOs;

class BuildingDTO
{
    public ?string $name = null;
    public ?int $unit_id = null;

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->name = $data['name'] ?? null;
        $dto->unit_id = isset($data['unit_id']) && is_numeric($data['unit_id']) ? (int)$data['unit_id'] : null;
        return $dto;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'unit_id' => $this->unit_id,
        ];
    }
}
