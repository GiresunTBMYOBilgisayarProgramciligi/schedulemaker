<?php

namespace App\DTOs;

class ProgramDTO
{
    public ?string $name;
    public ?int $department_id;
    public ?bool $active;

    public function __construct(?string $name, ?int $department_id, ?bool $active)
    {
        $this->name = $name;
        $this->department_id = $department_id;
        $this->active = $active;
    }

    public static function fromArray(array $data): self
    {
        $active = null;
        if (isset($data['active'])) {
            $active = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active === null && ($data['active'] === 'on' || $data['active'] === 1 || $data['active'] === '1')) {
                $active = true;
            }
        }

        return new self(
            $data['name'] ?? null,
            isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null,
            $active
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'department_id' => $this->department_id,
            'active' => $this->active
        ];
    }
}
