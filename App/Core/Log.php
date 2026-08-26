<?php

namespace App\Core;

use App\Middlewares\AuthMiddleware;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * Centralized logging helper for the whole application.
 * Provides a shared logger instance and a unified context builder.
 */
class Log
{
    /** @var Logger|null */
    private static ?Logger $logger = null;

    /**
     * Get the shared Monolog logger instance.
     */
    public static function logger(): Logger
    {
        if (self::$logger instanceof Logger) {
            return self::$logger;
        }

        // Build the logger here so the project doesn't depend on LoggerFactory
        $channel = 'app';
        $logger = new Logger($channel);

        // DB handler: write everything from Debug and above
        $dbLevel = ($_ENV['DEBUG'] ?? 'false') === 'true' ? Level::Debug : Level::Info;
        $dbHandler = new DbLogHandler($dbLevel, true);
        $logger->pushHandler($dbHandler);

        $logDir = $_ENV['LOG_PATH'] ?? dirname(__DIR__, 2) . '/Logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        // Optional fallback to file in DEBUG
        if (($_ENV['DEBUG'] ?? 'false') === 'true') {
            $debugFile = $logDir . '/debug.log';
            if (!file_exists($debugFile) ? is_writable($logDir) : is_writable($debugFile)) {
                $debugHandler = new StreamHandler($debugFile, Level::Debug);
                $logger->pushHandler(new FilterHandler($debugHandler, Level::Debug, Level::Debug));
            }
        }
        
        $infoFile = $logDir . '/info.log';
        if (!file_exists($infoFile) ? is_writable($logDir) : is_writable($infoFile)) {
            $infoHandler = new StreamHandler($infoFile, Level::Info);
            $logger->pushHandler(new FilterHandler($infoHandler, Level::Info, Level::Info));
        }

        $errorFile = $logDir . '/error.log';
        if (!file_exists($errorFile) ? is_writable($logDir) : is_writable($errorFile)) {
            $logger->pushHandler(new StreamHandler($errorFile, Level::Error, true));
        }

        self::$logger = $logger;
        return self::$logger;
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
