<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Core\Gate;
use App\Models\User;
use App\Models\Building;
use App\Exceptions\AuthorizationException;
use App\Middlewares\AuthMiddleware;
use function App\Helpers\hasRole;

class GateTest extends BaseTestCase
{
    private function setUserSession(?int $userId): void
    {
        $sessionKey = $_ENV["SESSION_KEY"] ?? 'user_id';
        if ($userId === null) {
            unset($_SESSION[$sessionKey]);
        } else {
            $_SESSION[$sessionKey] = $userId;
        }

        // Reset AuthMiddleware cache
        $ref = new \ReflectionClass(AuthMiddleware::class);
        $propResolved = $ref->getProperty('isResolved');
        $propResolved->setValue(null, false);
        $propUser = $ref->getProperty('currentUser');
        $propUser->setValue(null, null);
    }

    public function testGuestDeniedAccess(): void
    {
        $this->setUserSession(null);

        $building = new Building();
        $building->id = 1;
        $building->unit_id = 1;

        $this->assertFalse(Gate::check('view', $building));
        $this->assertFalse(Gate::allowsRole('admin'));
    }

    public function testAdminHasFullAccess(): void
    {
        $unitId = $this->insert('units', ['name' => 'Gate Test Unit ' . rand(1000, 9999), 'type' => 'myo']);
        $adminId = $this->insert('users', [
            'mail' => 'admin_gate_' . rand(1000, 9999) . '@test.com',
            'name' => 'Admin',
            'last_name' => 'User',
            'role' => 'admin',
            'unit_id' => $unitId
        ]);

        $this->setUserSession($adminId);

        $building = new Building();
        $building->id = 1;
        $building->unit_id = $unitId;

        $this->assertTrue(Gate::check('view', $building));
        $this->assertTrue(Gate::check('create', Building::class));
        $this->assertTrue(Gate::check('delete', $building));
        $this->assertTrue(Gate::allowsRole('manager'));
        $this->assertTrue(Gate::allowsRole('admin'));
    }

    public function testAuthorizeThrowsAuthorizationException(): void
    {
        $this->setUserSession(null);

        $this->expectException(AuthorizationException::class);
        Gate::authorizeRole('admin');
    }

    public function testAllowsRoleHierarchy(): void
    {
        $unitId = $this->insert('units', ['name' => 'Role Unit ' . rand(1000, 9999), 'type' => 'myo']);
        $managerId = $this->insert('users', [
            'mail' => 'manager_' . rand(1000, 9999) . '@test.com',
            'name' => 'Manager',
            'last_name' => 'User',
            'role' => 'manager',
            'unit_id' => $unitId
        ]);

        $this->setUserSession($managerId);

        $this->assertTrue(Gate::allowsRole('submanager'));
        $this->assertTrue(Gate::allowsRole('lecturer'));
        $this->assertTrue(Gate::allowsRole('manager'));
        $this->assertFalse(Gate::allowsRole('admin'));

        // Reverse check (rol seviyesi küçük eşit olanlar)
        $this->assertTrue(Gate::allowsRole('admin', true));
        $this->assertFalse(Gate::allowsRole('lecturer', true));
    }

    public function testHasRoleWithUserObjectAndEnum(): void
    {
        $user = new User();
        $user->role = 'secretary';

        $this->assertTrue(Gate::hasRole($user, \App\Enums\UserRole::Secretary));
        $this->assertTrue(Gate::hasRole($user, \App\Enums\UserRole::PayrollOfficer));
        $this->assertTrue(Gate::hasRole($user, \App\Enums\UserRole::DepartmentHead));
        $this->assertTrue(Gate::hasRole($user, \App\Enums\UserRole::Lecturer));
        $this->assertFalse(Gate::hasRole($user, \App\Enums\UserRole::SubManager));
        $this->assertFalse(Gate::hasRole($user, \App\Enums\UserRole::Admin));

        // User model metodu testi
        $this->assertTrue($user->hasRole(\App\Enums\UserRole::Secretary));
        $this->assertTrue($user->hasRole(\App\Enums\UserRole::PayrollOfficer));
        $this->assertFalse($user->hasRole(\App\Enums\UserRole::Admin));

        // Global helper fonksiyonu testi
        $this->assertTrue(hasRole(\App\Enums\UserRole::Secretary, $user));
        $this->assertTrue(hasRole(\App\Enums\UserRole::PayrollOfficer, $user));
        $this->assertFalse(hasRole(\App\Enums\UserRole::Admin, $user));

        // PayrollOfficer rolü ile test
        $payrollUser = new User();
        $payrollUser->role = 'payroll_officer';
        $this->assertTrue(Gate::hasRole($payrollUser, \App\Enums\UserRole::PayrollOfficer));
        $this->assertTrue(Gate::hasRole($payrollUser, \App\Enums\UserRole::DepartmentHead));
        $this->assertFalse(Gate::hasRole($payrollUser, \App\Enums\UserRole::Secretary));
        $this->assertFalse(Gate::hasRole($payrollUser, \App\Enums\UserRole::SubManager));
    }
}
