<?php

namespace App\DTOs;

class DepartmentDTO
{
    public ?string $name = null;
    public ?int $chairperson_id = null;
    public ?bool $active = null;

    /**
     * Dizi verisinden DTO nesnesi oluşturur.
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->name = $data['name'] ?? null;
        $dto->chairperson_id = isset($data['chairperson_id']) && $data['chairperson_id'] !== '' ? (int)$data['chairperson_id'] : null;
        
        // Checkbox'dan gelen veriyi boolean'a çevir (frontend "1" veya true gönderebilir, işaretlenmezse gelmez)
        if (isset($data['active'])) {
            $dto->active = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN);
        } else {
            // Eğer update işleminde checkbox işaretsizse form datada hiç gelmeyebilir, 
            // bunu validator/controller tarafında false veya null geçerek kontrol edeceğiz.
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
            'name' => $this->name,
            'chairperson_id' => $this->chairperson_id,
            'active' => $this->active,
        ];
    }
}
