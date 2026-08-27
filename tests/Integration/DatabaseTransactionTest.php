<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Core\Database;
use App\Models\Building;

class DatabaseTransactionTest extends BaseTestCase
{
    public function testTransactionRollsBackOnException(): void
    {
        // BaseTestCase'in başlattığı transaction'ı rollback yapıp temiz başlangıç yapalım
        if ($this->getDb()->inTransaction()) {
            $this->getDb()->rollBack();
        }

        $unitId = $this->insert('units', ['name' => 'Tx Unit ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);

        try {
            Database::transaction(function () use ($unitId) {
                // 1. Bir bina ekle
                $stmt = $this->getDb()->prepare("INSERT INTO buildings (name, unit_id) VALUES (?, ?)");
                $stmt->execute(['Rollback Test Binası', $unitId]);

                // 2. Kasıtlı bir hata fırlat
                throw new \RuntimeException('İşlem sırasında beklenmedik hata!');
            });
        } catch (\RuntimeException $e) {
            // Hatayı yakala
        }

        // Eklenen binanın rollback edildiğini ve veritabanında olmadığını doğrula
        $stmt = $this->getDb()->prepare("SELECT * FROM buildings WHERE name = ? AND unit_id = ?");
        $stmt->execute(['Rollback Test Binası', $unitId]);
        $record = $stmt->fetch();

        // Sonraki testler için transaction'ı tekrar başlat
        $this->getDb()->beginTransaction();

        $this->assertFalse($record, 'Transaction hata aldığında yapılan tüm veritabanı işlemleri geri alınmalıydı.');
    }

    public function testTransactionCommitsOnSuccess(): void
    {
        if ($this->getDb()->inTransaction()) {
            $this->getDb()->rollBack();
        }

        $unitId = $this->insert('units', ['name' => 'Tx Commit Unit ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);

        $result = Database::transaction(function () use ($unitId) {
            $stmt = $this->getDb()->prepare("INSERT INTO buildings (name, unit_id) VALUES (?, ?)");
            $stmt->execute(['Commit Test Binası', $unitId]);
            return (int)$this->getDb()->lastInsertId();
        });

        $this->assertGreaterThan(0, $result);
        $building = (new Building())->find($result);

        // Sonraki testler için transaction'ı tekrar başlat
        $this->getDb()->beginTransaction();

        $this->assertNotNull($building);
        $this->assertEquals('Commit Test Binası', $building->name);
    }
}
