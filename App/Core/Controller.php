<?php

namespace App\Core;

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
     * Yüklenen dosyayı doğrular ve geçerli dosya dizisini döndürür.
     *
     * @param array $files $_FILES veya gelen dosya dizisi
     * @param string $fileKey
     * @param array $allowedExtensions
     * @param int $maxSizeBytes
     * @return array
     * @throws \Exception
     */
    protected function validateUploadedFile(
        array $files,
        string $fileKey = 'file',
        array $allowedExtensions = ['xlsx', 'xls', 'csv'],
        int $maxSizeBytes = 10485760
    ): array {
        $uploadedFile = $files[$fileKey] ?? null;
        if (!$uploadedFile || empty($uploadedFile['tmp_name'])) {
            throw new \Exception("Dosya yüklenmedi");
        }

        if (isset($uploadedFile['size']) && $uploadedFile['size'] > $maxSizeBytes) {
            $maxMB = (int) ($maxSizeBytes / (1024 * 1024));
            throw new \Exception("Yüklenen dosya boyutu en fazla {$maxMB}MB olabilir.");
        }

        $ext = strtolower(pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $extList = implode(', ', array_map(fn($e) => '.' . $e, $allowedExtensions));
            throw new \Exception("Yalnızca Excel (.xlsx, .xls) veya .csv formatındaki dosyalar desteklenmektedir.");
        }

        return $uploadedFile;
    }
}