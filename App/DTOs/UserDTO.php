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
        public ?int $programId = null
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
            programId: !empty($validatedData['program_id']) ? (int) $validatedData['program_id'] : null
        );
    }

    /**
     * User modelinin veritabanına kayıt işlemi için dizi formuna geri çevirir (Snake Case).
     * 
     * @return array
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'last_name' => $this->lastName,
            'mail' => $this->mail,
            'role' => $this->role->value,
        ];

        if ($this->password !== null) {
            $data['password'] = $this->password;
        }

        if ($this->title !== null) {
            $data['title'] = $this->title->value;
        }

        if ($this->departmentId !== null) {
            $data['department_id'] = $this->departmentId;
        }

        if ($this->programId !== null) {
            $data['program_id'] = $this->programId;
        }

        return $data;
    }
}
