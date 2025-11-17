<?php

namespace App\Core;

use Exception;
use PDO;
use Monolog\Logger;

class Controller
{
    public PDO $database;

    public function __construct()
    {
        $this->database = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS'], [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
    }

    /**
     * Shared application logger for all controllers.
     */
    protected function logger(): Logger
    {
        return LoggerFactory::getLogger();
    }

    /**
     * Standard logging context used across controllers.
     * Adds current user, caller method, URL and IP.
     */
    protected function logContext(array $extra = []): array
    {
        $username = null;
        $userId = null;
        try {
            $user = (new \App\Controllers\UserController())->getCurrentUser();
            if ($user) {
                $username = trim(($user->title ? $user->title . ' ' : '') . $user->name . ' ' . $user->last_name);
                $userId = $user->id;
            }
        } catch (\Throwable $t) {
            // ignore user detection failures
        }
        // Try to detect caller function
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $bt[1]['function'] ?? null;
        return array_merge([
            'username' => $username,
            'user_id' => $userId,
            'class' => static::class,
            'method' => $caller,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], $extra);
    }

    /**
     * filtre ile belirtilen koşullara uyan veri sayısını döner
     * @param array|null $filters
     * @return int
     * @throws Exception
     */
    public function getCount(?array $filters = []): int
    {
        // Alt sınıfta table_name tanımlı mı kontrol et
        if (!property_exists($this, 'modelName')) {
            throw new Exception('Model adı özelliği tanımlı değil.');
        }
        $model = new $this->modelName;
        return $model->get()->where($filters)->count();
    }


    /**
     * Parametre olarak gelen alanlara göre otomatik koşul oluşturur ve koşullara uyan dersleri dizi olarak döner. Her bir eleman Lesson nesnesidir
     * @param array|null $filters
     * @return array
     * @throws Exception
     */
    public function getListByFilters(?array $filters = null): array
    {
        // Alt sınıfta table_name tanımlı mı kontrol et
        if (!property_exists($this, 'modelName')) {
            throw new Exception('Model adı özelliği tanımlı değil.');
        }
        $model = new $this->modelName;
        return $model->get()->where($filters)->all();
    }

}