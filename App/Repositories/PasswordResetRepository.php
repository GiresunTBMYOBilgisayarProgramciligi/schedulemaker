<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PasswordResetRepository
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * E-postaya ait eski token'ı siler.
     */
    public function deleteByEmail(string $email): void
    {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
    }

    /**
     * Yeni bir şifre sıfırlama token'ı oluşturur.
     */
    public function createToken(string $email, string $token): void
    {
        $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$email, $token]);
    }

    /**
     * Verilen e-posta ve token'ın geçerli olup olmadığını (son 1 saat içinde) kontrol eder.
     */
    public function findValidToken(string $email, string $token): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND created_at >= NOW() - INTERVAL 1 HOUR");
        $stmt->execute([$email, $token]);
        $record = $stmt->fetch();
        return $record ?: null;
    }
}
