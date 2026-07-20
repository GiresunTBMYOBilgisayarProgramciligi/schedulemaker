<?php

namespace App\DTOs;

class DepartmentDTO
{
    public ?string $name = null;
    public ?int $chairperson_id = null;
    public ?int $unit_id = null;
    public ?bool $active = null;

    /**
     * Dizi verisinden DTO nesnesi oluşturur.
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->name           = $data['name'] ?? null;
        $dto->chairperson_id = isset($data['chairperson_id']) && $data['chairperson_id'] !== '' && $data['chairperson_id'] !== '0' && $data['chairperson_id'] !== 0 ? (int)$data['chairperson_id'] : null;
        $dto->unit_id        = isset($data['unit_id']) && $data['unit_id'] !== '' && $data['unit_id'] !== '0' && $data['unit_id'] !== 0 ? (int)$data['unit_id'] : null;

        if (isset($data['active'])) {
            $dto->active = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $dto->active = false;
        }

        return $dto;
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
