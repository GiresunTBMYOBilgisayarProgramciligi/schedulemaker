<?php

namespace App\DTOs;

/**
 * Kullanıcı girişi için kimlik doğrulama verilerini taşıyan kesin tipli DTO.
 */
readonly class LoginDTO
{
    public function __construct(
        public string $mail,
        public string $password,
        public bool $rememberMe = false
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mail: trim($data['mail'] ?? ''),
            password: (string)($data['password'] ?? ''),
            rememberMe: !empty($data['remember_me']) && filter_var($data['remember_me'], FILTER_VALIDATE_BOOLEAN)
        );
    }

    public function toArray(): array
    {
        return [
            'mail'        => $this->mail,
            'password'    => $this->password,
            'remember_me' => $this->rememberMe,
        ];
    }
}
