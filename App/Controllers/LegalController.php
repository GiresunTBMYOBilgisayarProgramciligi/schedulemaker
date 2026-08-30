<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Repositories\UserConsentRepository;
use Exception;

class LegalController extends Controller
{
    private UserConsentRepository $consentRepo;

    public function __construct()
    {
        parent::__construct();
        $this->consentRepo = new UserConsentRepository();
    }

    /**
     * Oturum açmış kullanıcının onay durumunu döndürür.
     *
     * @return array
     */
    public function checkConsentStatus(): array
    {
        $user = AuthMiddleware::user();
        if (!$user) {
            return [
                "status" => "error",
                "msg" => "Oturum açılmamış."
            ];
        }

        $accepted = $this->consentRepo->hasAcceptedAll($user->id);

        return [
            "status" => "success",
            "has_accepted" => $accepted
        ];
    }

    /**
     * Kullanıcının KVKK ve Gizlilik Politikası onayını kaydeder.
     *
     * @param array $requestData
     * @return array
     */
    public function acceptConsent(array $requestData = []): array
    {
        $user = AuthMiddleware::user();
        if (!$user) {
            return [
                "status" => "error",
                "msg" => "Onay vermek için oturum açmalısınız."
            ];
        }

        $version = is_string($requestData['version'] ?? null) && !empty(trim($requestData['version']))
            ? substr(trim($requestData['version']), 0, 20)
            : 'v1.0';

        $rawIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (str_contains($rawIp, ',')) {
            $rawIp = trim(explode(',', $rawIp)[0]);
        }
        $ipAddress = filter_var($rawIp, FILTER_VALIDATE_IP) ? $rawIp : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            // Hem KVKK Aydınlatma Metnini hem Gizlilik Politikasını onayla
            $this->consentRepo->recordConsent($user->id, 'kvkk_clarification', $version, $ipAddress, $userAgent);
            $this->consentRepo->recordConsent($user->id, 'privacy_policy', $version, $ipAddress, $userAgent);

            $this->logger()->info("Kullanıcı yasal metinleri onayladı", $this->logContext([
                'user_id' => $user->id,
                'version' => $version,
                'ip' => $ipAddress
            ]));

            return [
                "status" => "success",
                "msg" => "Bilgilendirme ve onayınız başarıyla kaydedildi."
            ];
        } catch (Exception $e) {
            $this->logger()->error("Yasal metin onayı kaydedilirken hata: " . $e->getMessage(), $this->logContext([
                'exception' => $e
            ]));

            return [
                "status" => "error",
                "msg" => "Onay kaydedilirken bir hata oluştu: " . $e->getMessage()
            ];
        }
    }
}
