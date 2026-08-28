<?php

namespace App\Services;

use App\Models\MailQueue;
use App\Enums\MailQueueStatus;
use App\Repositories\MailQueueRepository;
use App\Core\Mailer;
use App\Core\Log;
use PHPMailer\PHPMailer\PHPMailer;
use Exception;
use Throwable;
use function App\Helpers\getSettingValue;

class MailQueueService extends BaseService
{
    private MailQueueRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new MailQueueRepository();
    }

    /**
     * E-postayı kuyruğa ekler.
     *
     * @param string $toEmail
     * @param string|null $toName
     * @param string $subject
     * @param string $body
     * @param string|null $altBody
     * @param array $attachments [ ['name' => '...', 'content' => '...', 'encoding' => 'base64', 'type' => '...'], ... ]
     * @return int Eklenen kuyruk ID'si
     * @throws Exception
     */
    public function enqueue(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $body,
        ?string $altBody = null,
        array $attachments = []
    ): int {
        $queue = new MailQueue();
        $queue->fill([
            'to_email'      => trim($toEmail),
            'to_name'       => $toName ? trim($toName) : null,
            'subject'       => $subject,
            'body'          => $body,
            'alt_body'      => $altBody,
            'attachments'   => !empty($attachments) ? json_encode($attachments, JSON_UNESCAPED_UNICODE) : null,
            'status'        => MailQueueStatus::Pending->value,
            'attempts'      => 0,
            'error_message' => null,
            'created_at'    => date('Y-m-d H:i:s')
        ]);

        $queue->create();

        $this->logger->info("E-posta kuyruğa eklendi: {$toEmail} - {$subject}", $this->logContext([
            'queue_id' => $queue->id,
            'to_email' => $toEmail,
            'subject'  => $subject
        ]));

        return $queue->id;
    }

    /**
     * Kuyruktaki bekleyen e-postaları parça parça ve eşzamanlı kilit korumasıyla işler.
     *
     * @param int|null $batchSize Tek seferde işlenecek e-posta sayısı (null ise veritabanı ayarından okunur)
     * @param int|null $maxAttempts Maksimum deneme sayısı (null ise veritabanı ayarından okunur)
     * @return array ['processed' => int, 'sent' => int, 'failed' => int]
     */
    public function processQueue(?int $batchSize = null, ?int $maxAttempts = null): array
    {
        $batchSize = ($batchSize !== null && $batchSize > 0) ? (int)$batchSize : (int)getSettingValue('mail_batch_size', 'mail', 10);
        $maxAttempts = ($maxAttempts !== null && $maxAttempts > 0) ? (int)$maxAttempts : (int)getSettingValue('mail_max_attempts', 'mail', 3);

        $pendingItems = $this->repository->getPendingItems($batchSize, $maxAttempts);

        if (empty($pendingItems)) {
            return [
                'processed' => 0,
                'sent'      => 0,
                'failed'    => 0
            ];
        }

        $sentCount = 0;
        $failedCount = 0;
        $processedCount = 0;

        foreach ($pendingItems as $item) {
            // Eşzamanlı yarış durumlarını (Race condition) önlemek için atomik kilitleme
            $locked = $this->repository->atomicLockItem($item->id);
            if (!$locked) {
                // Başka bir iş parçacığı veya cron bu kaydı zaten aldı
                continue;
            }

            $processedCount++;
            $item->attempts = (int)$item->attempts + 1;
            $item->status = MailQueueStatus::Processing->value;

            $success = $this->sendQueuedItem($item);
            if ($success) {
                $item->status = MailQueueStatus::Sent->value;
                $item->sent_at = date('Y-m-d H:i:s');
                $item->error_message = null;
                $item->update();
                $sentCount++;
            } else {
                $item->status = MailQueueStatus::Failed->value;
                $item->update();
                $failedCount++;
            }
        }

        $this->logger->info("E-posta kuyruğu işlendi: {$sentCount} başarılı, {$failedCount} başarısız.", $this->logContext([
            'processed' => $processedCount,
            'sent'      => $sentCount,
            'failed'    => $failedCount
        ]));

        return [
            'processed' => $processedCount,
            'sent'      => $sentCount,
            'failed'    => $failedCount
        ];
    }

    /**
     * Tek bir kuyruk kaydını e-posta olarak gönderir.
     *
     * @param MailQueue $item
     * @return bool
     */
    private function sendQueuedItem(MailQueue $item): bool
    {
        try {
            $mailDriver = getSettingValue('mail_driver', 'mail', 'log');

            // Simülasyon / Test modu kontrolü
            if ($mailDriver !== 'smtp' || ($_ENV['APP_ENV'] ?? '') === 'testing' || defined('PHPUNIT_RUNNING')) {
                $this->logger->info("Kuyruk e-postası simülasyon modunda işlendi: {$item->to_email} - {$item->subject}", $this->logContext([
                    'queue_id' => $item->id
                ]));
                return true;
            }

            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host       = getSettingValue('smtp_host', 'mail', 'localhost');
            $mailer->SMTPAuth   = true;
            $mailer->Username   = getSettingValue('smtp_user', 'mail', '');
            $mailer->Password   = getSettingValue('smtp_pass', 'mail', '');
            $mailer->SMTPSecure = getSettingValue('smtp_secure', 'mail', PHPMailer::ENCRYPTION_STARTTLS);
            $mailer->Port       = (int)getSettingValue('smtp_port', 'mail', 587);
            $mailer->CharSet    = 'UTF-8';

            $fromEmail = getSettingValue('mail_from', 'mail', 'noreply@localhost');
            $fromName  = getSettingValue('mail_from_name', 'mail', 'Schedule Maker');
            $mailer->setFrom($fromEmail, $fromName);

            $mailer->addAddress($item->to_email, $item->to_name ?? '');
            $mailer->isHTML(true);
            $mailer->Subject = $item->subject;
            $mailer->Body    = $item->body;
            $mailer->AltBody = $item->alt_body ?? strip_tags(str_replace(['<br>', '</li>', '</p>', '</tr>'], "\n", $item->body));

            // Ekleri ekle
            if (!empty($item->attachments)) {
                $attachments = json_decode($item->attachments, true);
                if (is_array($attachments)) {
                    foreach ($attachments as $att) {
                        $name = $att['name'] ?? 'attachment';
                        $content = $att['content'] ?? '';
                        $encoding = $att['encoding'] ?? 'base64';
                        $type = $att['type'] ?? 'application/octet-stream';

                        if (!empty($content)) {
                            $decodedContent = ($encoding === 'base64') ? base64_decode($content) : $content;
                            $mailer->addStringAttachment($decodedContent, $name, 'base64', $type);
                        }
                    }
                }
            }

            return $mailer->send();
        } catch (Throwable $e) {
            $item->error_message = $e->getMessage();
            $this->logger->error("Kuyruk e-postası gönderilemedi: {$item->to_email} - {$e->getMessage()}", $this->logContext([
                'queue_id'  => $item->id,
                'exception' => $e
            ]));
            return false;
        }
    }

    /**
     * Kuyruk istatistiklerini döndürür.
     *
     * @return array
     */
    public function getQueueStats(): array
    {
        return $this->repository->getQueueStats();
    }

    /**
     * Başarısız olmuş kayıtları tekrar denemek üzere bekleyen durumuna alır.
     *
     * @return int Güncellenen kayıt sayısı
     */
    public function retryFailed(): int
    {
        $count = $this->repository->retryFailed();
        $this->logger->info("Başarısız e-postalar yeniden kuyruğa alındı: {$count} adet", $this->logContext([
            'count' => $count
        ]));

        return $count;
    }

    /**
     * Başarıyla gönderilmiş eski kayıtları temizler.
     *
     * @return int Silinen kayıt sayısı
     */
    public function clearSentLogs(): int
    {
        $count = $this->repository->clearSentLogs();
        $this->logger->info("Başarıyla gönderilmiş kuyruk kayıtları temizlendi: {$count} adet", $this->logContext([
            'count' => $count
        ]));

        return $count;
    }

    /**
     * Belirli bir kuyruk kaydını siler.
     *
     * @param int $id
     * @return bool
     */
    public function deleteItem(int $id): bool
    {
        $item = $this->repository->find($id);
        if (!$item) {
            return false;
        }

        $deleted = $item->delete();
        if ($deleted) {
            $this->logger->info("Kuyruk kaydı silindi: #{$id}", $this->logContext(['id' => $id]));
        }
        return (bool)$deleted;
    }

    /**
     * Kuyruktaki e-postaları filtreli ve sıralı getirir.
     *
     * @param string|null $status
     * @param int $limit
     * @return MailQueue[]
     */
    public function getItems(?string $status = null, int $limit = 100): array
    {
        return $this->repository->getFilteredItems($status, $limit);
    }
}
