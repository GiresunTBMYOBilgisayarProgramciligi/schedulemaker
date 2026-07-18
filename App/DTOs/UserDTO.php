<?php

namespace App\DTOs;

use App\Enums\UserRole;
use App\Enums\UserTitle;

/**
 * Kullanıcı oluşturma veya güncelleme isteğinden gelen doğrulanmış (validated) veriyi taşıyan nesne.
 */
readonly class UserDTO
{
    public function __construct(
        public string $name,
        public string $lastName,
        public string $mail,
        public UserRole $role,
        public ?string $password = null,
        public ?UserTitle $title = null,
        public ?int $departmentId = null,
        public ?int $programId = null,
        public ?int $unitId = null
    ) {
    }

    /**
     * Gelen dizi formatındaki doğrulanmış POST verisini DTO nesnesine çevirir.
     * 
     * @param array $validatedData Validator'dan geçmiş güvenli veri
     * @return self
     */
    public static function fromArray(array $validatedData): self
    {
        return new self(
            name: $validatedData['name'],
            lastName: $validatedData['last_name'],
            mail: $validatedData['mail'],
            role: UserRole::from($validatedData['role']),
            password: !empty($validatedData['password']) ? $validatedData['password'] : null,
            title: !empty($validatedData['title']) ? UserTitle::tryFrom($validatedData['title']) : null,
            departmentId: !empty($validatedData['department_id']) ? (int) $validatedData['department_id'] : null,
            programId: !empty($validatedData['program_id']) ? (int) $validatedData['program_id'] : null,
            unitId: !empty($validatedData['unit_id']) ? (int) $validatedData['unit_id'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'last_name' => $this->lastName,
            'mail' => $this->mail,
            'role' => $this->role->value,
            'password' => $this->password,
            'title' => $this->title !== null ? $this->title->value : null,
            'department_id' => $this->departmentId,
            'program_id' => $this->programId,
            'unit_id' => $this->unitId,
        ];
    }
}
