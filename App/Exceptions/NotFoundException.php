<?php

namespace App\Exceptions;

/**
 * Sayfa veya kaynak bulunamadığında (404) fırlatılan özel istisna sınıfı.
 */
class NotFoundException extends AppException
{
    public function __construct(
        string $message = "Aradığınız sayfa veya kaynak bulunamadı.",
        array $context = [],
        int $code = 404,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $context, $code, $previous);
    }
}
