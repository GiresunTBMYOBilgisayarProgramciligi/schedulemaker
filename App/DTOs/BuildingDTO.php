<?php

namespace App\DTOs;

class BuildingDTO
{
    public ?string $name = null;

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->name = $data['name'] ?? null;
        return $dto;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
