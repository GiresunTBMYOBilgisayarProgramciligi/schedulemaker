<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;

abstract class BaseTestCase extends TestCase
{
    protected static $db;

    public static function setUpBeforeClass(): void
    {
        // Test ortamı için .env verilerini geçersiz kıl (phpunit.xml'den geliyor)
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Veritabanı bağlantısı al ve her test başında transaction başlat
        $this->getDb()->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Her test sonunda transaction rollback yaparak DB'yi temiz tut
        if (self::$db && self::$db->inTransaction()) {
            self::$db->rollBack();
        }
        parent::tearDown();
    }

    protected function getDb()
    {
        if (!self::$db) {
            // Singleton bağlantıyı sıfırla ki test bazlı izolasyon olsun
            $ref = new \ReflectionClass(Database::class);
            $prop = $ref->getProperty('connection');
            $prop->setAccessible(true);
            $prop->setValue(null, null);

            self::$db = Database::getConnection();
        }
        return self::$db;
    }

    /**
     * Test verisi oluşturmak için yardımcı metod
     */
    protected function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int)$this->getDb()->lastInsertId();
    }
}
