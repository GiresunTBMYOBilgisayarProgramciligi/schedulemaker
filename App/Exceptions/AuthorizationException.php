<?php

namespace App\Exceptions;

class AuthorizationException extends AppException
{
    public function __construct(string $message = "Bu işlem için yetkiniz bulunmamaktadır.", array $context = [], int $code = 403, ?\Exception $previous = null)
    {
        parent::__construct($message, $context, $code, $previous);
    }
}
