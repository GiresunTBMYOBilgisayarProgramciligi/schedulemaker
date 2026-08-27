<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\UserService;
use App\DTOs\UserDTO;
use App\DTOs\LoginDTO;
use App\Models\User;
use App\Enums\UserRole;

class UserServiceTest extends BaseTestCase
{
    private UserService $service;
    private int $unitId;
    private int $deptId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserService();
        $this->unitId = $this->insert('units', [
            'name' => 'User Test Birimi ' . rand(1000, 9999),
            'type' => 'myo',
            'active' => 1
        ]);
        $this->deptId = $this->insert('departments', [
            'name' => 'User Test Bölüm ' . rand(1000, 9999),
            'unit_id' => $this->unitId,
            'active' => 1
        ]);
    }

    public function testSaveNewUserHashesPassword(): void
    {
        $dto = UserDTO::fromArray([
            'name' => 'Mehmet',
            'last_name' => 'Demir',
            'mail' => 'mehmet_service_' . rand(1000, 9999) . '@test.com',
            'role' => UserRole::Lecturer->value,
            'password' => 'DuzMetinSifre123',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);

        $userId = $this->service->saveNew($dto);
        $this->assertGreaterThan(0, $userId);

        $user = (new User())->find($userId);
        $this->assertEquals('Mehmet', $user->name);
        $this->assertEquals('Demir', $user->last_name);
        $this->assertEquals(UserRole::Lecturer->value, $user->role);
        // Şifre hashlenmiş olmalı, düz metin olmamalı
        $this->assertNotEquals('DuzMetinSifre123', $user->password);
        $this->assertTrue(password_verify('DuzMetinSifre123', $user->password));
    }

    public function testLoginSuccessAndFailure(): void
    {
        $email = 'login_test_' . rand(1000, 9999) . '@test.com';
        $dto = UserDTO::fromArray([
            'name' => 'Ali',
            'last_name' => 'Can',
            'mail' => $email,
            'role' => UserRole::Lecturer->value,
            'password' => 'GizliParola123',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);
        $userId = $this->service->saveNew($dto);

        // Başarılı giriş testi
        $loginDto = LoginDTO::fromArray([
            'mail' => $email,
            'password' => 'GizliParola123',
            'remember_me' => false
        ]);
        $this->service->login($loginDto);
        $this->assertEquals($userId, $_SESSION[$_ENV['SESSION_KEY']]);

        // Yanlış şifre testi (Exception fırlatmalı)
        $wrongLoginDto = LoginDTO::fromArray([
            'mail' => $email,
            'password' => 'YanlisSifre',
            'remember_me' => false
        ]);
        $this->expectException(\Exception::class);
        $this->service->login($wrongLoginDto);
    }

    public function testUpdateUser(): void
    {
        $dto = UserDTO::fromArray([
            'name' => 'Ayşe',
            'last_name' => 'Kara',
            'mail' => 'ayse_' . rand(1000, 9999) . '@test.com',
            'role' => UserRole::Lecturer->value,
            'password' => 'Sifre123456',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);
        $userId = $this->service->saveNew($dto);

        $user = (new User())->find($userId);
        $user->name = 'Ayşe Fatma';
        $this->service->updateUser($user);

        $updated = (new User())->find($userId);
        $this->assertEquals('Ayşe Fatma', $updated->name);
    }
}
