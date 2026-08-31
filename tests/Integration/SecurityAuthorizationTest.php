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

    public function testPayrollOfficerCanViewOwnUnitSchedulesAndIsDeniedCrossUnit(): void
    {
        // 1. Birim 1 ve Mutemet 1
        $unit1Id = $this->insert('units', ['name' => 'Payroll Unit 1 ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);
        $payrollOfficer1Id = $this->insert('users', [
            'mail' => 'payroll1_' . rand(1000, 9999) . '@test.com',
            'name' => 'Mutemet',
            'last_name' => 'Bir',
            'role' => 'payroll_officer',
            'unit_id' => $unit1Id
        ]);

        // Birim 1'e bağlı bölüm, program ve ders programı
        $dept1Id = $this->insert('departments', ['name' => 'Payroll Dept 1', 'unit_id' => $unit1Id, 'active' => 1]);
        $dept1 = (new \App\Models\Department())->find($dept1Id);
        $prog1Id = $this->insert('programs', ['name' => 'Payroll Prog 1', 'department_id' => $dept1Id, 'active' => 1]);
        $prog1 = (new \App\Models\Program())->find($prog1Id);

        $sched1Id = $this->insert('schedules', [
            'academic_year' => '2025 - 2026',
            'semester' => 'Güz',
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => $prog1Id,
            'is_published' => 1
        ]);
        $schedule1 = (new \App\Models\Schedule())->find($sched1Id);

        // 2. Birim 2 ve Program/Bölüm
        $unit2Id = $this->insert('units', ['name' => 'Payroll Unit 2 ' . rand(1000, 9999), 'type' => 'fakulte', 'active' => 1]);
        $dept2Id = $this->insert('departments', ['name' => 'Payroll Dept 2', 'unit_id' => $unit2Id, 'active' => 1]);
        $dept2 = (new \App\Models\Department())->find($dept2Id);
        $prog2Id = $this->insert('programs', ['name' => 'Payroll Prog 2', 'department_id' => $dept2Id, 'active' => 1]);
        $prog2 = (new \App\Models\Program())->find($prog2Id);

        // Mutemet 1 oturumu aç
        $this->setUserSession($payrollOfficer1Id);

        // Kendi birimindeki bölüm ve programı görebilmeli
        $this->assertTrue(Gate::check('view', $dept1));
        $this->assertTrue(Gate::check('view', $prog1));
        $this->assertTrue(Gate::check('view', $schedule1));

        // Başka birimin bölümünü ve programını görememeli
        $this->assertFalse(Gate::check('view', $dept2));
        $this->assertFalse(Gate::check('view', $prog2));

        // Kendi birimindeki programı dahi düzenleyememeli veya silememeli (sadece görüntüleme / çıktı)
        $this->assertFalse(Gate::check('update', $schedule1));
        $this->assertFalse(Gate::check('delete', $schedule1));
        $this->assertFalse(Gate::check('publish_schedule', $schedule1));

        // Sistem yönetimine erişememeli
        $this->assertFalse(Gate::allowsRole('admin'));
        $this->assertFalse(Gate::allowsRole('submanager'));

        // Dashboard verisi hatasız yüklenmeli
        $adminPageController = new \App\Controllers\AdminPageController();
        $payrollUser = (new \App\Models\User())->find($payrollOfficer1Id);
        $dashboardData = $adminPageController->getIndexPageData($payrollUser, new \App\Core\AssetManager());

        $this->assertEquals('payroll_officer', $dashboardData['dashboardRole']);
        $this->assertArrayHasKey('stats', $dashboardData);
        $this->assertGreaterThanOrEqual(1, $dashboardData['stats']['departments']);
        $this->assertGreaterThanOrEqual(1, $dashboardData['stats']['programs']);
        $this->assertArrayHasKey('lessons', $dashboardData['stats']);
        $this->assertArrayHasKey('academics', $dashboardData['stats']);
    }
}
