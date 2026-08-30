<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Models\User;
use App\Models\UserConsent;
use App\Repositories\UserConsentRepository;
use App\Controllers\LegalController;

class UserConsentTest extends BaseTestCase
{
    private function setUserSession(int $userId): void
    {
        $sessionKey = $_ENV["SESSION_KEY"] ?? 'user_id';
        $_SESSION[$sessionKey] = $userId;
        $this->resetAuth();
        $_SESSION[$sessionKey] = $userId;
    }

    public function testUserConsentWorkflow(): void
    {
        // 1. Test kullanıcısı oluştur
        $unitId = $this->insert('units', ['name' => 'KVKK Test Birim ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);
        $userId = $this->insert('users', [
            'mail' => 'kvkk_' . rand(1000, 9999) . '@test.com',
            'name' => 'Akademisyen',
            'last_name' => 'Test',
            'role' => 'lecturer',
            'unit_id' => $unitId
        ]);

        $repo = new UserConsentRepository();

        // 2. Başlangıçta kullanıcının onayı olmamalı
        $this->assertFalse($repo->hasAccepted($userId, 'kvkk_clarification', 'v1.0'));
        $this->assertFalse($repo->hasAccepted($userId, 'privacy_policy', 'v1.0'));
        $this->assertFalse($repo->hasAcceptedAll($userId));

        // 3. Tek tek onay kaydı
        $consent = $repo->recordConsent($userId, 'kvkk_clarification', 'v1.0', '192.168.1.100', 'Mozilla/5.0 Test');
        $this->assertInstanceOf(UserConsent::class, $consent);
        $this->assertEquals($userId, $consent->user_id);
        $this->assertEquals('kvkk_clarification', $consent->consent_type);
        $this->assertEquals('v1.0', $consent->version);
        $this->assertEquals('192.168.1.100', $consent->ip_address);

        $this->assertTrue($repo->hasAccepted($userId, 'kvkk_clarification', 'v1.0'));
        $this->assertFalse($repo->hasAcceptedAll($userId)); // Çünkü privacy_policy henüz onaylanmadı

        // 4. Privacy policy onayı
        $repo->recordConsent($userId, 'privacy_policy', 'v1.0', '192.168.1.100');
        $this->assertTrue($repo->hasAccepted($userId, 'privacy_policy', 'v1.0'));
        $this->assertTrue($repo->hasAcceptedAll($userId));

        // 5. Idempotent test (tekrar kayıt yapıldığında duplicate oluşturmamalı)
        $consentsBefore = count($repo->getUserConsents($userId));
        $repo->recordConsent($userId, 'kvkk_clarification', 'v1.0', '192.168.1.100');
        $consentsAfter = count($repo->getUserConsents($userId));
        $this->assertEquals($consentsBefore, $consentsAfter);
    }

    public function testLegalControllerAcceptConsent(): void
    {
        // 1. Kullanıcı oluştur ve oturum aç
        $unitId = $this->insert('units', ['name' => 'Legal Controller Birim ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);
        $userId = $this->insert('users', [
            'mail' => 'legal_' . rand(1000, 9999) . '@test.com',
            'name' => 'Hoca',
            'last_name' => 'Deneme',
            'role' => 'lecturer',
            'unit_id' => $unitId
        ]);
        $this->setUserSession($userId);

        $_SERVER['REMOTE_ADDR'] = '10.0.0.42';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test Browser';

        $controller = new LegalController();

        // 2. Başlangıç durumu kontrolü
        $statusBefore = $controller->checkConsentStatus();
        $this->assertEquals('success', $statusBefore['status']);
        $this->assertFalse($statusBefore['has_accepted']);

        // 3. Onay gönderimi
        $acceptResponse = $controller->acceptConsent(['version' => 'v1.0']);
        $this->assertEquals('success', $acceptResponse['status']);

        // 4. Sonraki durum kontrolü
        $statusAfter = $controller->checkConsentStatus();
        $this->assertEquals('success', $statusAfter['status']);
        $this->assertTrue($statusAfter['has_accepted']);
    }
}
