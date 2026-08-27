<?php

namespace App\DTOs;

/**
 * Bina verisi için kesin tipli DTO.
 */
readonly class BuildingDTO
{
    public function __construct(
        public ?string $name = null,
        public ?int $unit_id = null
    ) {
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            unit_id: isset($data['unit_id']) && is_numeric($data['unit_id']) ? (int)$data['unit_id'] : null
        );
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
