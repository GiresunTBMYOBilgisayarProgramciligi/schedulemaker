<?php

namespace App\Repositories;

use App\Models\MailQueue;
use App\Enums\MailQueueStatus;
use App\Core\Database;
use PDO;

/**
 * mail_queue tablosu sorgulamaları ve veri tabanı işlemleri için Repository sınıfı.
 */
class MailQueueRepository extends BaseRepository
{
    protected string $modelClass = MailQueue::class;

    /**
     * Kuyruk istatistiklerini döner.
     *
     * @return array
     */
    public function getQueueStats(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM mail_queue GROUP BY status");
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        return [
            'pending'    => (int)($results[MailQueueStatus::Pending->value] ?? 0),
            'processing' => (int)($results[MailQueueStatus::Processing->value] ?? 0),
            'sent'       => (int)($results[MailQueueStatus::Sent->value] ?? 0),
            'failed'     => (int)($results[MailQueueStatus::Failed->value] ?? 0),
            'total'      => array_sum($results)
        ];
    }

    /**
     * İşlenmeye hazır bekleyen e-posta kayıtlarını FIFO (First In, First Out) sıralamasıyla getirir.
     *
     * @param int $batchSize
     * @param int $maxAttempts
     * @return MailQueue[]
     */
    public function getPendingItems(int $batchSize, int $maxAttempts): array
    {
        /** @var MailQueue[] $items */
        $items = (new MailQueue())->get()
            ->where([
                'status' => [
                    'in' => [MailQueueStatus::Pending->value, MailQueueStatus::Failed->value]
                ],
                'attempts' => ['<' => $maxAttempts]
            ])
            ->orderBy('id', 'ASC')
            ->limit($batchSize)
            ->all();

        return $items;
    }

    /**
     * Bir kaydı işleme almak için atomik olarak 'processing' durumuna kilitler ve attempts sayısını artırır.
     * Eşzamanlı cron / worker çakışmalarını (%100) önler.
     *
     * @param int $id
     * @return bool Kilitleme başarılı ise true
     */
    public function atomicLockItem(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE mail_queue SET status = :processing, attempts = attempts + 1 WHERE id = :id AND status IN (:pending, :failed)");
        $stmt->execute([
            ':processing' => MailQueueStatus::Processing->value,
            ':id'         => $id,
            ':pending'    => MailQueueStatus::Pending->value,
            ':failed'     => MailQueueStatus::Failed->value
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Başarısız olmuş kayıtları yeniden kuyruğa alır.
     *
     * @return int Güncellenen kayıt sayısı
     */
    public function retryFailed(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE mail_queue SET status = :pending, attempts = 0, error_message = NULL WHERE status = :failed");
        $stmt->execute([
            ':pending' => MailQueueStatus::Pending->value,
            ':failed'  => MailQueueStatus::Failed->value
        ]);

        return $stmt->rowCount();
    }

    /**
     * Başarıyla gönderilmiş kayıtları temizler.
     *
     * @return int Silinen kayıt sayısı
     */
    public function clearSentLogs(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM mail_queue WHERE status = :sent");
        $stmt->execute([
            ':sent' => MailQueueStatus::Sent->value
        ]);

        return $stmt->rowCount();
    }

    /**
     * Kuyruk kayıtlarını filtreli getirir.
     *
     * @param string|null $status
     * @param int $limit
     * @return MailQueue[]
     */
    public function getFilteredItems(?string $status = null, int $limit = 100): array
    {
        $model = new MailQueue();
        $query = $model->get();

        if ($status && in_array($status, [MailQueueStatus::Pending->value, MailQueueStatus::Processing->value, MailQueueStatus::Sent->value, MailQueueStatus::Failed->value])) {
            $query->where(['status' => $status]);
        }

        $query->orderBy('id', 'DESC')->limit($limit);

        return $query->all();
    }
}
