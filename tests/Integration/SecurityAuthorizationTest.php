<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Core\Gate;
use App\Models\Building;
use App\Exceptions\AuthorizationException;

class SecurityAuthorizationTest extends BaseTestCase
{
    private function setUserSession(int $userId): void
    {
        $sessionKey = $_ENV["SESSION_KEY"] ?? 'user_id';
        $_SESSION[$sessionKey] = $userId;
        $this->resetAuth();
        $_SESSION[$sessionKey] = $userId;
    }

    public function testCrossUnitAccessIsDeniedForSecretary(): void
    {
        // 1. Birim ve Sekreteri
        $unit1Id = $this->insert('units', ['name' => 'Güvenlik Birim 1 ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);
        $secretary1Id = $this->insert('users', [
            'mail' => 'sec1_' . rand(1000, 9999) . '@test.com',
            'name' => 'Sekreter',
            'last_name' => 'Bir',
            'role' => 'secretary',
            'unit_id' => $unit1Id
        ]);

        // 2. Birim ve Binası
        $unit2Id = $this->insert('units', ['name' => 'Güvenlik Birim 2 ' . rand(1000, 9999), 'type' => 'fakulte', 'active' => 1]);
        $building2Id = $this->insert('buildings', ['name' => 'Birim 2 Binası', 'unit_id' => $unit2Id]);
        $building2 = (new Building())->find($building2Id);

        // Sekreter 1 olarak oturum aç
        $this->setUserSession($secretary1Id);

        // Sekreter 1, Birim 2'nin binasını görüntüleyememeli ve güncelleyememeli
        $this->assertFalse(Gate::check('view', $building2));
        $this->assertFalse(Gate::check('update', $building2));
        $this->assertFalse(Gate::check('delete', $building2));

        // Gate::authorize Exception fırlatmalı
        $this->expectException(AuthorizationException::class);
        Gate::authorize('update', $building2);
    }

    public function testLecturerCannotAccessAdminSettings(): void
    {
        $unitId = $this->insert('units', ['name' => 'Lecturer Unit ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);
        $lecturerId = $this->insert('users', [
            'mail' => 'lecturer_sec_' . rand(1000, 9999) . '@test.com',
            'name' => 'Hoca',
            'last_name' => 'Test',
            'role' => 'lecturer',
            'unit_id' => $unitId
        ]);

        $this->setUserSession($lecturerId);

        $this->assertFalse(Gate::allowsRole('admin'));
        $this->assertFalse(Gate::allowsRole('manager'));

        $this->expectException(AuthorizationException::class);
        Gate::authorizeRole('admin');
    }
}
