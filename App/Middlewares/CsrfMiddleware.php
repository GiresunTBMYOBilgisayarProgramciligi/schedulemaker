<?php

namespace App\Middlewares;

use App\Core\Csrf;
use App\Core\Log;
use App\Exceptions\AuthorizationException;

/**
 * CSRF (Cross-Site Request Forgery) doğrulama ara katmanı.
 *
 * Durum değiştiren (POST, PUT, DELETE, PATCH) tüm HTTP isteklerinde
 * geçerli bir CSRF belirteci (token) bulunmasını zorunlu kılar.
 */
class CsrfMiddleware
{
    /**
     * CSRF kontrolünü yürütür.
     *
     * @param bool $skipCsrf İstek CSRF denetiminden muaf mı
     * @throws ForbiddenException CSRF doğrulaması başarısız olursa
     */
    public static function handle(bool $skipCsrf = false): void
    {
        if (!Csrf::$enabled || $skipCsrf) {
            return;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Güvenli (safe/idempotent) HTTP metodları CSRF denetiminden muaftır
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        // Gönderilen CSRF belirtecini tespit et
        $submittedToken = self::resolveSubmittedToken();

        if (!Csrf::validate($submittedToken)) {
            Log::logger()->warning('CSRF doğrulaması başarısız oldu.', Log::context(null, [
                'method' => $method,
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'has_token' => !empty($submittedToken)
            ]));

            self::abortForbidden();
        }
    }

    /**
     * İstek başlıklarından veya gövdesinden CSRF belirtecini ayıklar.
     */
    protected static function resolveSubmittedToken(): ?string
    {
        // 1. HTTP Başlığı: X-CSRF-TOKEN
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // 2. HTTP Başlığı: X-XSRF-TOKEN
        if (!empty($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            return (string)$_SERVER['HTTP_X_XSRF_TOKEN'];
        }

        // 3. Form verisi: _csrf_token veya csrf_token
        if (!empty($_POST['_csrf_token'])) {
            return (string)$_POST['_csrf_token'];
        }

        if (!empty($_POST['csrf_token'])) {
            return (string)$_POST['csrf_token'];
        }

        // 4. JSON Payload
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains(strtolower($contentType), 'application/json')) {
            $rawInput = file_get_contents('php://input');
            if ($rawInput) {
                $decoded = json_decode($rawInput, true);
                if (is_array($decoded) && !empty($decoded['_csrf_token'])) {
                    return (string)$decoded['_csrf_token'];
                }
                if (is_array($decoded) && !empty($decoded['csrf_token'])) {
                    return (string)$decoded['csrf_token'];
                }
            }
        }

        return null;
    }

    /**
     * Doğrulama başarısız olduğunda 403 Forbidden yanıtı üretir.
     *
     * @throws AuthorizationException AJAX dışındaki web isteklerinde
     */
    protected static function abortForbidden(): void
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'msg' => 'Güvenlik doğrulaması (CSRF) başarısız oldu veya oturum süreniz doldu. Lütfen sayfayı yenileyip tekrar deneyiniz.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        throw new AuthorizationException('Geçersiz veya süresi dolmuş CSRF belirteci. Lütfen sayfayı yenileyip tekrar deneyiniz.');
    }
}
