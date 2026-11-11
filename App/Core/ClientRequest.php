<?php

namespace App\Core;

/**
 * İstemci IP adresi ve HTTPS algılama gibi, ters proxy/yük dengeleyici
 * arkasında çalışırken dikkat edilmesi gereken istek bilgilerini tek
 * bir yerden sağlar.
 *
 * X-Forwarded-* başlıkları varsayılan olarak GÜVENİLMEZ: bu başlıklar
 * istemci tarafından kolayca sahtelenebilir (örn. rate-limit'i atlatmak
 * için farklı sahte IP'ler bildirmek). Sadece isteğin doğrudan geldiği
 * adres (REMOTE_ADDR) TRUSTED_PROXIES ortam değişkenindeki listede ise
 * (uygulamanın önündeki kendi ters proxy'si/yük dengeleyici) bu
 * başlıklara güvenilir. Varsayılan (TRUSTED_PROXIES boş) durumda hiçbir
 * başlığa güvenilmez; davranış mevcut haliyle aynıdır.
 */
class ClientRequest
{
    /**
     * İstemcinin gerçek IP adresini döndürür.
     */
    public static function ip(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (self::isTrustedProxy($remoteAddr) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            $clientIp  = trim($forwarded[0]);
            if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                return $clientIp;
            }
        }

        return $remoteAddr;
    }

    /**
     * İstek HTTPS üzerinden mi geldi (doğrudan ya da güvenilir bir ters
     * proxy'nin bildirdiği şekilde).
     */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        if (self::isTrustedProxy($remoteAddr) && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower(trim((string)$_SERVER['HTTP_X_FORWARDED_PROTO'])) === 'https';
        }

        return false;
    }

    /**
     * @return string[]
     */
    private static function trustedProxies(): array
    {
        $raw = $_ENV['TRUSTED_PROXIES'] ?? '';
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private static function isTrustedProxy(string $remoteAddr): bool
    {
        if ($remoteAddr === '') {
            return false;
        }

        return in_array($remoteAddr, self::trustedProxies(), true);
    }
}
