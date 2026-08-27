<?php

namespace App\DTOs;

/**
 * Bölüm verisi için kesin tipli DTO.
 */
readonly class DepartmentDTO
{
    public function __construct(
        public ?string $name = null,
        public ?int $chairperson_id = null,
        public ?int $unit_id = null,
        public ?bool $active = null
    ) {
    }

    /**
     * Dizi verisinden DTO nesnesi oluşturur.
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $active = false;
        if (isset($data['active'])) {
            $active = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN);
        }

        return new self(
            name: $data['name'] ?? null,
            chairperson_id: isset($data['chairperson_id']) && $data['chairperson_id'] !== '' && $data['chairperson_id'] !== '0' && $data['chairperson_id'] !== 0 ? (int)$data['chairperson_id'] : null,
            unit_id: isset($data['unit_id']) && $data['unit_id'] !== '' && $data['unit_id'] !== '0' && $data['unit_id'] !== 0 ? (int)$data['unit_id'] : null,
            active: $active
        );
    }

    /**
     * DTO'yu diziye çevirir (DB kaydı veya Model doldurmak için).
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name'           => $this->name,
            'chairperson_id' => $this->chairperson_id,
            'unit_id'        => $this->unit_id,
            'active'         => $this->active ? 1 : 0,
        ];
    }
}
