<?php

namespace App\DTOs;

/**
 * Program verisi için kesin tipli DTO.
 */
readonly class ProgramDTO
{
    public function __construct(
        public ?string $name = null,
        public ?int $department_id = null,
        public ?bool $active = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        if (isset($data['active'])) {
            $active = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active === null && ($data['active'] === 'on' || $data['active'] === 1 || $data['active'] === '1')) {
                $active = true;
            }
        } else {
            $active = false;
        }

        return new self(
            name: $data['name'] ?? null,
            department_id: isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null,
            active: $active
        );
    }

    public function toArray(): array
    {
        return [
            'name'          => $this->name,
            'department_id' => $this->department_id,
            'active'        => $this->active,
        ];
    }
}
