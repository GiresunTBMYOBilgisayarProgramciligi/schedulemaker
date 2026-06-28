<?php

namespace App\DTOs;

class SettingDTO
{
    public ?string $group;
    public ?string $key;
    public ?string $value;
    public ?string $type;

    public function __construct(
        ?string $group,
        ?string $key,
        ?string $value,
        ?string $type
    ) {
        $this->group = $group;
        $this->key = $key;
        $this->value = $value;
        $this->type = $type;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['group'] ?? null,
            $data['key'] ?? null,
            $data['value'] ?? null,
            $data['type'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
        ];
    }
}
