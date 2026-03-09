<?php

namespace App\Repositories;

use PDO;
use App\Core\Database;
use App\Core\Model;
use Exception;

/**
 * Tüm repository sınıfları için temel sınıf
 * 
 * Sorumluluklar:
 * - Database query'leri
 * - Model mapping
 * - CRUD operasyonları
 */
abstract class BaseRepository
{
    protected PDO $db;
    protected string $modelClass;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * ID'ye göre kayıt bulur
     * @param int $id
     * @return Model|null
     * @throws Exception
     */
    public function find(int $id): ?Model
    {
        /** @var Model $model */
        $model = new $this->modelClass;
        return $model->find($id);
    }

    /**
     * Kriterlere göre kayıtları bulur
     * @param array $criteria Filtre kriterleri
     * @return array
     * @throws Exception
     */
    public function findBy(array $criteria): array
    {
        /** @var Model $model */
        $model = new $this->modelClass;
        return $model->get()->where($criteria)->all();
    }

    /**
     * İlk kaydı bulur
     * @param array $criteria
     * @return Model|null
     * @throws Exception
     */
    public function findOneBy(array $criteria): ?Model
    {
        /** @var Model $model */
        $model = new $this->modelClass;
        return $model->get()->where($criteria)->first();
    }

    /**
     * Tüm kayıtları getirir
     * @return array
     * @throws Exception
     */
    public function findAll(): array
    {
        /** @var Model $model */
        $model = new $this->modelClass;
        return $model->get()->all();
    }

    /**
     * Yeni kayıt oluşturur
     * @param array $data
     * @return Model
     * @throws Exception
     */
    public function create(array $data): Model
    {
        /** @var Model $model */
        $model = new $this->modelClass;
        $model->fill($data);
        $model->create();
        return $model;
    }

    /**
     * Kayıt sayısını döner
     * @param array $criteria
     * @return int
     * @throws Exception
     */
    public function count(array $criteria = []): int
    {
        /** @var Model $model */
        $model = new $this->modelClass;
        return $model->get()->where($criteria)->count();
    }
}
