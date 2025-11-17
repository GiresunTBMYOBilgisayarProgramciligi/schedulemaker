<?php

namespace App\Core;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use PDO;
use Throwable;

/**
 * Monolog handler that writes logs into MySQL using PDO
 */
class DbLogHandler extends AbstractProcessingHandler
{
    private PDO $pdo;

    public function __construct($level = \Monolog\Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        // Create dedicated PDO to avoid relying on Controller/Model constructors
        $dsn = "mysql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . ";dbname=" . ($_ENV['DB_NAME'] ?? '');
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, // avoid throwing in handler
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Ensure utf8mb4
        $this->pdo->exec("SET NAMES utf8mb4");
        // Optionally ensure logs table exists in dev or when explicitly enabled
        $autoCreate = (defined('DEBUG_MODE') && DEBUG_MODE) || filter_var($_ENV['LOGS_AUTO_CREATE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        if ($autoCreate) {
            $this->ensureLogsTable();
        }
    }

    private function ensureLogsTable(): void
    {
        try {
            $sqlJson = "CREATE TABLE IF NOT EXISTS logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                username VARCHAR(100) NULL,
                user_id INT NULL,
                level VARCHAR(20) NOT NULL,
                channel VARCHAR(50) NULL,
                message TEXT NOT NULL,
                class VARCHAR(255) NULL,
                method VARCHAR(255) NULL,
                `function` VARCHAR(255) NULL,
                `file` VARCHAR(255) NULL,
                line INT NULL,
                url TEXT NULL,
                ip VARCHAR(45) NULL,
                trace LONGTEXT NULL,
                context JSON NULL,
                extra JSON NULL,
                INDEX (created_at),
                INDEX (level),
                INDEX (user_id)
            ) ENGINE=INNODB";
            $this->pdo->exec($sqlJson);
        } catch (\Throwable $e1) {
            // Fallback for older MySQL/MariaDB without JSON type support
            try {
                $sqlText = "CREATE TABLE IF NOT EXISTS logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    username VARCHAR(100) NULL,
                    user_id INT NULL,
                    level VARCHAR(20) NOT NULL,
                    channel VARCHAR(50) NULL,
                    message TEXT NOT NULL,
                    class VARCHAR(255) NULL,
                    method VARCHAR(255) NULL,
                    `function` VARCHAR(255) NULL,
                    `file` VARCHAR(255) NULL,
                    line INT NULL,
                    url TEXT NULL,
                    ip VARCHAR(45) NULL,
                    trace LONGTEXT NULL,
                    context LONGTEXT NULL,
                    extra LONGTEXT NULL,
                    INDEX (created_at),
                    INDEX (level),
                    INDEX (user_id)
                ) ENGINE=INNODB";
                $this->pdo->exec($sqlText);
            } catch (\Throwable $e2) {
                error_log('[DbLogHandler] ensureLogsTable failed (fallback): ' . $e2->getMessage());
            }
        }
    }

    protected function write(LogRecord $record): void
    {
        try {
            $ctx = $record->context ?? [];
            $extra = $record->extra ?? [];

            // Flatten context items
            $username = $ctx['username'] ?? null;
            $user_id = isset($ctx['user_id']) ? (int)$ctx['user_id'] : null;
            $method = $ctx['method'] ?? null;
            $class = $ctx['class'] ?? null;
            $function = $ctx['function'] ?? null;
            $file = $ctx['file'] ?? null;
            $line = isset($ctx['line']) ? (int)$ctx['line'] : null;
            $url = $ctx['url'] ?? null;
            $ip = $ctx['ip'] ?? null;
            $trace = $ctx['trace'] ?? null;

            $stmt = $this->pdo->prepare(
                "INSERT INTO logs
                (`created_at`, `username`, `user_id`, `level`, `channel`, `message`, `class`, `method`, `function`, `file`, `line`, `url`, `ip`, `trace`, `context`, `extra`)
                VALUES (NOW(), :username, :user_id, :level, :channel, :message, :class, :method, :function, :file, :line, :url, :ip, :trace, :context, :extra)"
            );

            $stmt->execute([
                ':username' => $username,
                ':user_id' => $user_id,
                ':level' => $record->level->getName(),
                ':channel' => $record->channel,
                ':message' => $record->message,
                ':class' => $class,
                ':method' => $method,
                ':function' => $function,
                ':file' => $file,
                ':line' => $line,
                ':url' => $url,
                ':ip' => $ip,
                ':trace' => is_string($trace) ? $trace : (is_array($trace) ? json_encode($trace, JSON_UNESCAPED_UNICODE) : null),
                ':context' => json_encode($ctx, JSON_UNESCAPED_UNICODE),
                ':extra' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            // Last resort: write to PHP error log to avoid infinite loops
            error_log('[DbLogHandler] failed: ' . $e->getMessage());
        }
    }
}
