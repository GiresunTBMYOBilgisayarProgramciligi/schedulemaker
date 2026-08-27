<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Core\Database;
use App\Models\Building;

class DatabaseTransactionTest extends BaseTestCase
{
    /**
     * SAVEPOINT kullanarak nested transaction rollback davranışını doğrular.
     * BaseTestCase'in dış transaction'ı içinde kalır; tearDown temizler.
     */
    public function testTransactionRollsBackOnException(): void
    {
        $db = $this->getDb();

        // Test verisi — BaseTestCase tearDown'da rollback edilecek
        $unitId = $this->insert('units', ['name' => 'Tx Unit ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);

        // SAVEPOINT ile nested transaction simüle et
        $db->exec('SAVEPOINT sp_rollback_test');

        try {
            $stmt = $db->prepare("INSERT INTO buildings (name, unit_id) VALUES (?, ?)");
            $stmt->execute(['Rollback Test Binası', $unitId]);

            throw new \RuntimeException('İşlem sırasında beklenmedik hata!');

        } catch (\RuntimeException) {
            $db->exec('ROLLBACK TO SAVEPOINT sp_rollback_test');
            $db->exec('RELEASE SAVEPOINT sp_rollback_test');
        }

        // Savepoint geri alındı → bina kayıt olmamalı
        $stmt = $db->prepare("SELECT * FROM buildings WHERE name = ? AND unit_id = ?");
        $stmt->execute(['Rollback Test Binası', $unitId]);
        $record = $stmt->fetch();

        $this->assertFalse($record, 'Savepoint rollback sonrası bina kaydı veritabanında olmamalıydı.');
    }

    /**
     * Database::transaction() başarılı olduğunda sonucu döndürdüğünü doğrular.
     * Non-initiator modda çalışır (dış transaction var); veriler tearDown'da temizlenir.
     */
    public function testTransactionCommitsOnSuccess(): void
    {
        $unitId = $this->insert('units', ['name' => 'Tx Commit Unit ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);

        // Database::transaction dış transaction içinde → non-initiator
        $buildingId = Database::transaction(function () use ($unitId) {
            $stmt = $this->getDb()->prepare("INSERT INTO buildings (name, unit_id) VALUES (?, ?)");
            $stmt->execute(['Commit Test Binası', $unitId]);
            return (int)$this->getDb()->lastInsertId();
        });

        $this->assertGreaterThan(0, $buildingId);

        $building = (new Building())->find($buildingId);
        $this->assertNotNull($building);
        $this->assertEquals('Commit Test Binası', $building->name);
        // BaseTestCase::tearDown() bu veriyi rollback eder — veritabanında iz kalmaz
    }
}
