<?php

namespace App\DTOs;

use App\Enums\UnitType;

/**
 * Birim verisi için kesin tipli DTO.
 */
readonly class UnitDTO
{
    public function __construct(
        public ?string $name = null,
        public ?UnitType $type = null,
        public ?bool $active = null
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
            $type = $data['type'] instanceof UnitType
                ? $data['type']
                : UnitType::tryFrom($data['type']);
        }

        $active = false;
        if (isset($data['active'])) {
            $active = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN);
        }

        return new self(
            name: $data['name'] ?? null,
            type: $type,
            active: $active
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name'   => $this->name,
            'type'   => $this->type?->value,
            'active' => $this->active,
        ];
    }
}
