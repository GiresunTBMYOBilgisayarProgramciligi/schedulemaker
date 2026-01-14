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