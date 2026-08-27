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
        $this->resetAuth();
        // Veritabanı bağlantısı al ve her test başında transaction başlat
        $this->getDb()->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Her test sonunda transaction rollback yaparak DB'yi temiz tut
        if (self::$db && self::$db->inTransaction()) {
            self::$db->rollBack();
        }
        $this->resetAuth();
        parent::tearDown();
    }

    protected function resetAuth(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
        if (class_exists(\App\Middlewares\AuthMiddleware::class)) {
            $ref = new \ReflectionClass(\App\Middlewares\AuthMiddleware::class);
            if ($ref->hasProperty('isResolved')) {
                $propResolved = $ref->getProperty('isResolved');
                $propResolved->setValue(null, false);
            }
            if ($ref->hasProperty('currentUser')) {
                $propUser = $ref->getProperty('currentUser');
                $propUser->setValue(null, null);
            }
        }
    }

    protected function getDb()
    {
        if (!self::$db) {
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
