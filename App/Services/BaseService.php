<?php

namespace App\Services;

use PDO;
use App\Core\Database;
use Monolog\Logger;
use App\Core\Log;

/**
 * Tüm service sınıfları için temel sınıf
 * 
 * Sorumluluklar:
 * - Database bağlantısı yönetimi
 * - Transaction yönetimi
 * - Logging infrastructure
 */
abstract class BaseService
{
    protected PDO $db;
    protected Logger $logger;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->logger = Log::logger();
    }

    /**
     * Transaction başlatır (eğer aktif değilse)
     */
    protected function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $this->logger->debug('Transaction başlatıldı', Log::context($this));
        }
    }

    /**
     * Transaction'ı commit eder
     */
    protected function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
            $this->logger->debug('Transaction commit edildi', Log::context($this));
        }
    }

    /**
     * Transaction'ı rollback eder
     */
    protected function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
            $this->logger->warning('Transaction rollback edildi', Log::context($this));
        }
    }

    /**
     * Log context helper - service bilgisiyle birlikte
     * @param array $extra Ekstra context bilgileri
     * @return array
     */
    protected function logContext(array $extra = []): array
    {
        return Log::context($this, array_merge(['service' => static::class], $extra));
    }
}
