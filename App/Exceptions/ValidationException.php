<?php

namespace App\Exceptions;

/**
 * Validation hatası exception'ı
 * 
 * Veri doğrulama başarısız olduğunda fırlatılır
 */
class ValidationException extends AppException
{
    /**
     * @param string $message Hata mesajı
     * @param array $validationErrors Validation hataları listesi
     * @param array $context Ek context
     */
    public function __construct(
        string $message = 'Validation failed',
        array $validationErrors = [],
        array $context = []
    ) {
        // Validation error'larını mesaja ekle (log'da görünsün)
        if (!empty($validationErrors)) {
            $message .= ': ' . implode('; ', $validationErrors);
        }

        $context['validation_errors'] = $validationErrors;
        parent::__construct($message, $context);
    }

    /**
     * Validation hatalarını döner
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->context['validation_errors'] ?? [];
    }
}
