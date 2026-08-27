<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\Auth\PasswordResetService;
use App\DTOs\ForgotPasswordDTO;
use App\DTOs\ResetPasswordDTO;
use App\Models\User;
use App\Repositories\PasswordResetRepository;

class PasswordResetServiceTest extends BaseTestCase
{
    private PasswordResetService $service;
    private int $userId;
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PasswordResetService();
        $this->email = 'reset_' . rand(1000, 9999) . '@test.com';

        $this->userId = $this->insert('users', [
            'name' => 'Reset',
            'last_name' => 'User',
            'mail' => $this->email,
            'role' => 'lecturer',
            'password' => password_hash('EskiSifre123', PASSWORD_DEFAULT)
        ]);
    }

    public function testSendResetLinkCreatesToken(): void
    {
        $dto = new ForgotPasswordDTO($this->email);
        $this->service->sendResetLink($dto);

        $stmt = $this->getDb()->prepare("SELECT * FROM password_resets WHERE email = ?");
        $stmt->execute([$this->email]);
        $record = $stmt->fetch();

        $this->assertNotEmpty($record);
        $this->assertEquals($this->email, $record['email']);
        $this->assertNotEmpty($record['token']);
    }

    public function testResetPasswordWithValidToken(): void
    {
        $token = 'test_token_' . rand(1000, 9999);
        $resetRepo = new PasswordResetRepository();
        $resetRepo->createToken($this->email, $token);

        $resetDto = new ResetPasswordDTO(
            email: $this->email,
            token: $token,
            password: 'YeniGucluSifre123!',
            passwordConfirmation: 'YeniGucluSifre123!'
        );

        $this->service->resetPassword($resetDto);

        // Kullanıcı şifresi güncellenmiş olmalı
        $user = (new User())->find($this->userId);
        $this->assertTrue(password_verify('YeniGucluSifre123!', $user->password));

        // Token tablodan silinmiş olmalı
        $this->assertNull($resetRepo->findValidToken($this->email, $token));
    }

    public function testResetPasswordWithInvalidTokenThrowsException(): void
    {
        $resetDto = new ResetPasswordDTO(
            email: $this->email,
            token: 'gecersiz_token',
            password: 'YeniSifre123',
            passwordConfirmation: 'YeniSifre123'
        );

        $this->expectException(\Exception::class);
        $this->service->resetPassword($resetDto);
    }
}
