<?php

namespace App\Repositories;

use App\Models\UserConsent;
use Exception;

class UserConsentRepository extends BaseRepository
{
    protected string $modelClass = UserConsent::class;

    /**
     * Kullanıcının belirli bir onay tipini ve sürümünü kabul edip etmediğini kontrol eder.
     *
     * @param int $userId
     * @param string $consentType (örn: 'kvkk_clarification', 'privacy_policy')
     * @param string $version (örn: 'v1.0')
     * @return bool
     */
    public function hasAccepted(int $userId, string $consentType, string $version = 'v1.0'): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM user_consents 
            WHERE user_id = :user_id 
              AND consent_type = :consent_type 
              AND version = :version
        ");
        $stmt->execute([
            'user_id' => $userId,
            'consent_type' => $consentType,
            'version' => $version
        ]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Kullanıcının aktif olan tüm yasal metinleri onaylayıp onaylamadığını kontrol eder.
     *
     * @param int $userId
     * @param array $requiredConsents [['type' => 'kvkk_clarification', 'version' => 'v1.0'], ...]
     * @return bool
     */
    public function hasAcceptedAll(int $userId, array $requiredConsents = []): bool
    {
        if (empty($requiredConsents)) {
            $requiredConsents = [
                ['type' => 'kvkk_clarification', 'version' => 'v1.0'],
                ['type' => 'privacy_policy', 'version' => 'v1.0']
            ];
        }

        foreach ($requiredConsents as $rc) {
            if (!$this->hasAccepted($userId, $rc['type'], $rc['version'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Kullanıcı onayını kaydeder.
     *
     * @param int $userId
     * @param string $consentType
     * @param string $version
     * @param string $ipAddress
     * @param string|null $userAgent
     * @return UserConsent
     * @throws Exception
     */
    public function recordConsent(
        int $userId,
        string $consentType,
        string $version,
        string $ipAddress,
        ?string $userAgent = null
    ): UserConsent {
        // Zaten kayıtlı ise mevcut kaydı döndür (idempotent)
        if ($this->hasAccepted($userId, $consentType, $version)) {
            $existing = (new UserConsent())->where([
                'user_id' => $userId,
                'consent_type' => $consentType,
                'version' => $version
            ])->first();

            if ($existing) {
                return $existing;
            }
        }

        $consent = new UserConsent();
        $consent->user_id = $userId;
        $consent->consent_type = $consentType;
        $consent->version = $version;
        $consent->ip_address = $ipAddress;
        $consent->user_agent = $userAgent ? substr($userAgent, 0, 255) : null;
        $consent->create();

        return $consent;
    }

    /**
     * Kullanıcının onayladığı tüm kayıtları listeler.
     *
     * @param int $userId
     * @return UserConsent[]
     */
    public function getUserConsents(int $userId): array
    {
        return (new UserConsent())->where(['user_id' => $userId])->get()->all();
    }
}
