<?php

namespace App\DTOs;

use App\Enums\UnitType;

class UnitDTO
{
    public ?string $name = null;
    public ?UnitType $type = null;
    public ?bool $active = null;

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->name   = $data['name'] ?? null;

        if (isset($data['type'])) {
            $dto->type = UnitType::tryFrom($data['type']);
        }

        if (isset($data['active'])) {
            $dto->active = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $dto->active = false;
        }

        return $dto;
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
