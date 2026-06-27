<?php

namespace App\Core;

use App\Controllers\UserController;
use Exception;
use PDO;
use Monolog\Logger;

class Controller
{
    public PDO $database;

    public function __construct()
    {
        $this->database = Database::getConnection();
    }

    /**
     * Shared application logger for all controllers.
     */
    protected function logger(): Logger
    {
        return Log::logger();
    }

    /**
     * Standard logging context used across controllers.
     * Adds current user, caller method, URL and IP.
     */
    protected function logContext(array $extra = []): array
    {
        return Log::context($this, $extra);
    }
}