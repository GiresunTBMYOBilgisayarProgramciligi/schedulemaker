<?php

namespace App\Core;

use App\Middlewares\AuthMiddleware;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * Centralized logging helper for the whole application.
 * Provides channel-based rotating file logging and a unified context builder.
 */
class Log
{
    /** @var array<string, Logger> */
    private static array $loggers = [];

    /** @var Logger|null */
    private static ?Logger $customLogger = null;

    /** @var array{maxFiles: int, levelName: string}|null */
    private static ?array $cachedSettings = null;

    /** @var bool Re-entrancy guard to prevent infinite recursion during bootstrap */
    private static bool $isLoadingSettings = false;

    /**
     * Set a custom logger instance (e.g. for testing).
     */
    public static function setLogger(?Logger $logger): void
    {
        self::$customLogger = $logger;
    }

    /**
     * Reset the shared logger instances.
     */
    public static function reset(): void
    {
        self::$loggers = [];
        self::$customLogger = null;
        self::$cachedSettings = null;
        self::$isLoadingSettings = false;
    }

    /**
     * Log ayarlarını döngüsel bağımlılık ve aşırı bellek tüketimi oluşturmadan güvenle yükler.
     *
     * @return array{rotationPeriod: string, retentionDays: int, maxFiles: int, levelName: string, dateFormat: string}
     */
    private static function resolveLogSettings(): array
    {
        if (self::$cachedSettings !== null) {
            return self::$cachedSettings;
        }

        // Varsayılan güvenli ayarlar
        $defaults = [
            'rotationPeriod' => 'daily',
            'retentionDays'  => 14,
            'maxFiles'       => 14,
            'levelName'      => 'DEBUG',
            'dateFormat'     => 'Y-m-d',
        ];

        // Zaten ayar yükleniyorsa döngüyü kır ve varsayılanları dön
        if (self::$isLoadingSettings) {
            return $defaults;
        }

        self::$isLoadingSettings = true;

        try {
            // Service katmanını tetiklemeden doğrudan PDO üzerinden ayarları oku
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT `key`, `value` FROM `settings` WHERE `group` = 'log'");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            if (is_array($rows)) {
                // Rotasyon periyodu: daily (günlük), weekly (haftalık), monthly (aylık)
                if (!empty($rows['log_rotation_period'])) {
                    $period = strtolower(trim((string)$rows['log_rotation_period']));
                    if (in_array($period, ['daily', 'weekly', 'monthly'], true)) {
                        $defaults['rotationPeriod'] = $period;
                    }
                }

                // Saklama süresi (gün)
                if (isset($rows['log_retention_days']) && is_numeric($rows['log_retention_days'])) {
                    $days = (int)$rows['log_retention_days'];
                    if ($days > 0) {
                        $defaults['retentionDays'] = $days;
                    }
                } elseif (isset($rows['log_max_files']) && is_numeric($rows['log_max_files'])) {
                    // Geriye dönük uyumluluk
                    $days = (int)$rows['log_max_files'];
                    if ($days > 0) {
                        $defaults['retentionDays'] = $days;
                    }
                }

                // Minimum log seviyesi
                if (!empty($rows['log_level'])) {
                    $defaults['levelName'] = (string)$rows['log_level'];
                }
            }
        } catch (\Throwable) {
            // DB hazır değilse veya tablo yoksa sessizce varsayılanları kullan
        } finally {
            self::$isLoadingSettings = false;
        }

        // Periyot bazında Monolog dosya formatı ve maxFiles hesapla
        switch ($defaults['rotationPeriod']) {
            case 'weekly':
                $defaults['dateFormat'] = AppRotatingFileHandler::FILE_PER_WEEK; // Y-\WW (örn: 2026-W40)
                $defaults['maxFiles'] = max(1, (int)ceil($defaults['retentionDays'] / 7));
                break;
            case 'monthly':
                $defaults['dateFormat'] = AppRotatingFileHandler::FILE_PER_MONTH; // Y-m (örn: 2026-10)
                $defaults['maxFiles'] = max(1, (int)ceil($defaults['retentionDays'] / 30));
                break;
            case 'daily':
            default:
                $defaults['dateFormat'] = AppRotatingFileHandler::FILE_PER_DAY; // Y-m-d (örn: 2026-10-03)
                $defaults['maxFiles'] = max(1, $defaults['retentionDays']);
                break;
        }

        self::$cachedSettings = $defaults;
        return self::$cachedSettings;
    }

    /**
     * Belirtilen saklama süresinden daha eski log dosyalarını diskten temizler.
     *
     * @param string $logDir
     * @param int $retentionDays
     * @return int Silinen dosya sayısı
     */
    public static function cleanExpiredLogs(string $logDir, int $retentionDays): int
    {
        if ($retentionDays <= 0 || !is_dir($logDir)) {
            return 0;
        }

        $thresholdTime = time() - ($retentionDays * 86400);
        $files = glob($logDir . '/*.log') ?: [];
        $deleted = 0;

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $thresholdTime) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Get a Monolog logger instance for a specific channel.
     * Channels: 'app', 'auth', 'schedule', 'security', 'database', 'system', etc.
     */
    public static function channel(string $channel = 'app'): Logger
    {
        if (self::$customLogger instanceof Logger) {
            return self::$customLogger;
        }

        $channel = strtolower(trim($channel)) ?: 'app';

        if (isset(self::$loggers[$channel])) {
            return self::$loggers[$channel];
        }

        $logger = new Logger($channel);

        // Olası döngüsel çağrılarda aynı kanalın tekrar oluşturulmasını önlemek için erken sakla
        self::$loggers[$channel] = $logger;

        // Test ortamında logları devre dışı bırak (NullHandler)
        if (($_ENV['APP_ENV'] ?? '') === 'testing' || defined('PHPUNIT_RUNNING')) {
            $logger->pushHandler(new NullHandler());
            return $logger;
        }

        $logDir = $_ENV['LOG_PATH'] ?? dirname(__DIR__, 2) . '/Logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        // Ayarları güvenli ve optimize şekilde çek
        $settings = self::resolveLogSettings();
        $maxFiles = $settings['maxFiles'];
        $levelName = $settings['levelName'];
        $dateFormat = $settings['dateFormat'];

        // Eski dosyaları temizleme (GC: 1% olasılıkla tetiklenir, performansı etkilemez)
        if (mt_rand(1, 100) === 1) {
            self::cleanExpiredLogs($logDir, $settings['retentionDays']);
        }

        if (($_ENV['DEBUG'] ?? 'false') === 'true') {
            $minLevel = Level::Debug;
        } else {
            $minLevel = match (strtoupper($levelName)) {
                'DEBUG'     => Level::Debug,
                'INFO'      => Level::Info,
                'NOTICE'    => Level::Notice,
                'WARNING'   => Level::Warning,
                'ERROR'     => Level::Error,
                'CRITICAL'  => Level::Critical,
                'ALERT'     => Level::Alert,
                'EMERGENCY' => Level::Emergency,
                default     => Level::Info,
            };
        }

        // Rotasyonlu dosya işleyicisi: Logs/{channel}-{date}.log
        // filePermission null bırakılır; böylece farklı kullanıcılar (web www-data ve cli) chmod yetki hatası almaz
        $logFile = $logDir . '/' . $channel . '.log';
        $handler = new AppRotatingFileHandler($logFile, $maxFiles, $minLevel, true, null);
        $handler->setFilenameFormat('{filename}-{date}', $dateFormat);

        // JSON formatlayıcı: her log satırı tekil ve ayrıştırılabilir bir JSON nesnesidir
        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true);
        $handler->setFormatter($formatter);

        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * Get the default shared Monolog logger instance ('app' channel).
     */
    public static function logger(): Logger
    {
        return self::channel('app');
    }

    /**
     * Build a standard logging context used across the project.
     *
     * Fields: username, user_id, class, method, function, file, line, url, ip, [table]
     *
     * @param object|null $self The current object ($this) if available to better infer class/table.
     * @param array $extra Extra context fields to merge.
     * @return array
     */
    public static function context(?object $self = null, array $extra = []): array
    {
        $username = null;
        $userId = null;
        try {
            $user = AuthMiddleware::user();
            if ($user) {
                $username = $user->getFullName();
                $userId = $user->id;
            }
        } catch (\Throwable $t) {
            // ignore user detection failures
        }

        // Backtrace to infer caller
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

        $idx = 0;
        if (isset($bt[1]['function']) && $bt[1]['function'] === 'logContext') {
            $idx = 1;
        }

        $callerFunc = $bt[$idx + 1]['function'] ?? null;
        $callerClass = $bt[$idx + 1]['class'] ?? ($self ? get_class($self) : null);
        $file = $bt[$idx]['file'] ?? null;
        $line = $bt[$idx]['line'] ?? null;

        $ctx = [
            'username' => $username,
            'user_id' => $userId,
            'class' => $callerClass,
            'method' => $callerFunc,
            'function' => $callerFunc,
            'file' => $file,
            'line' => $line,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        // If the caller is a Model that defines table_name, include it
        if ($self && property_exists($self, 'table_name')) {
            /** @var mixed $self */
            try {
                $ctx['table'] = $self->table_name ?? null;
            } catch (\Throwable) {
                // ignore
            }
        }

        return array_merge($ctx, $extra);
    }
}
