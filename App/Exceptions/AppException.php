<?php

namespace App\Exceptions;

use Exception;

/**
 * Uygulama exception'ları için temel sınıf
 * 
 * Tüm custom exception'lar bu sınıftan türetilmelidir.
 * Context bilgisi taşıyabilir (logging, debugging için)
 */
abstract class AppException extends Exception
{
    protected array $context = [];

    /**
     * @param string $message Hata mesajı
     * @param array $context Ek context bilgileri (kullanıcı, kayıt ID'si vb.)
     * @param int $code Hata kodu
     * @param Exception|null $previous Önceki exception (exception chaining için)
     */
    public function __construct(
        string $message,
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Exception context bilgisini döner
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Context'e yeni bilgi ekler
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function addContext(string $key, mixed $value): void
    {
        $this->context[$key] = $value;
    }

    /**
     * Exception'ı array formatında döner (API response için)
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'type' => static::class,
            'context' => $this->context
        ];
    }
}
