<?php

namespace App\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class LoggerFactory
{
    private static ?Logger $logger = null;

    public static function getLogger(): Logger
    {
        if (self::$logger instanceof Logger) {
            return self::$logger;
        }
        $channel = 'app';
        $logger = new Logger($channel);

        // DB handler: accept info and above to record controller actions
        $dbHandler = new DbLogHandler(Level::Info, true);
        $logger->pushHandler($dbHandler);

        // Optional fallback to file in DEBUG
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $logPath = __DIR__ . '/../../var/app.log';
            // Ensure directory exists
            @mkdir(dirname($logPath), 0777, true);
            $logger->pushHandler(new StreamHandler($logPath, Level::Debug));
        }

        self::$logger = $logger;
        return self::$logger;
    }
}
