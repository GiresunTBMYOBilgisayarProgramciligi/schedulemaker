<?php

namespace App\DTOs;

readonly class ForgotPasswordDTO
{
    public function __construct(
        public string $email
    ) {
    }

    public static function fromArray(array $validatedData): self
    {
        return new self(
            email: $validatedData['email']
        );
    }
}
