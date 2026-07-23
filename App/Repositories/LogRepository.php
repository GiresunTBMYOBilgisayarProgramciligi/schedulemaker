<?php

namespace App\Repositories;

use App\Models\Log;
use App\Core\Database;
use PDO;
use Exception;

/**
 * logs tablosu sorgulamaları için Repository sınıfı.
 */
class LogRepository extends BaseRepository
{
    protected string $modelClass = Log::class;

    /**
     * En son N log kaydını döner.
     *
     * @param int $limit Getirilecek kayıt sayısı
     * @return Log[]
     */
    public function getRecent(int $limit = 10): array
    {
        try {
            $db   = Database::getConnection();
            $sql  = "SELECT * FROM logs ORDER BY id DESC LIMIT :limit";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function (array $row): Log {
                $log = new Log();
                $log->fill($row);
                return $log;
            }, $rows);
        } catch (Exception $e) {
            return [];
        }
    }
}
