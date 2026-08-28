<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Middlewares\AuthMiddleware;

class AuthMiddlewareTest extends BaseTestCase
{
    private function resetAuthMiddleware(): void
    {
        $ref = new \ReflectionClass(AuthMiddleware::class);
        $propResolved = $ref->getProperty('isResolved');
        $propResolved->setValue(null, false);
        $propUser = $ref->getProperty('currentUser');
        $propUser->setValue(null, null);
    }

    public function testCheckReturnsFalseWhenNoUser(): void
    {
        $sessionKey = $_ENV["SESSION_KEY"] ?? 'user_id';
        unset($_SESSION[$sessionKey]);
        $this->resetAuthMiddleware();

        $this->assertFalse(AuthMiddleware::check());
        $this->assertNull(AuthMiddleware::user());
    }

    public function testCheckReturnsTrueWhenUserSessionExists(): void
    {
        $deptId = $this->insert('departments', ['name' => 'Auth Mid Dept ' . rand(1000, 9999)]);
        $userId = $this->insert('users', [
            'mail' => 'auth_mid_' . rand(1000, 9999) . '@test.com',
            'name' => 'Auth',
            'last_name' => 'User',
            'role' => 'lecturer',
            'department_id' => $deptId
        ]);

        $sessionKey = $_ENV["SESSION_KEY"] ?? 'user_id';
        $_SESSION[$sessionKey] = $userId;
        $this->resetAuthMiddleware();

        $this->assertTrue(AuthMiddleware::check());
        $user = AuthMiddleware::user();
        $this->assertNotNull($user);
        $this->assertEquals($userId, $user->id);
    }
}
