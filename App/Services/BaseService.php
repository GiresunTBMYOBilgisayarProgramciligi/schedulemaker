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
    protected string $logChannel = 'app';

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->logger = Log::channel($this->logChannel);
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

    /**
     * Veritabanı transaction sarmalayıcısı.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws \Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        return Database::transaction($callback);
    }
}
