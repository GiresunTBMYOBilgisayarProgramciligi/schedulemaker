<?php

namespace App\DTOs;

/**
 * Sistem ayarı verisi için kesin tipli DTO.
 */
readonly class SettingDTO
{
    public function __construct(
        public ?string $group = null,
        public ?string $key = null,
        public ?string $value = null,
        public ?string $type = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            group: $data['group'] ?? null,
            key: $data['key'] ?? null,
            value: isset($data['value']) ? (string)$data['value'] : null,
            type: $data['type'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'key'   => $this->key,
            'value' => $this->value,
            'type'  => $this->type,
        ];
    }
}
