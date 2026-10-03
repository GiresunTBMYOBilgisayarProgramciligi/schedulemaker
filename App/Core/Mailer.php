<?php

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use function App\Helpers\getSettingValue;
use App\Core\Log;
use App\Core\View;
use App\Services\MailQueueService;

/**
 * Uygulamanın e-posta gönderim işlemlerini sağlayan temel sınıf.
 */
abstract class Mailer
{
    protected PHPMailer $mailer;

    /**
     * Testlerde sahte modun aktif olup olmadığını belirtir.
     */
    protected static bool $fake = false;

    /**
     * Gönderilen sahte e-postaların listesi.
     * @var array<int, array{to: array, subject: string, body: string, altBody: string, attachments: array, mailer: string}>
     */
    protected static array $sentMails = [];

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->setup();
    }

    /**
     * Test ortamında veya sahte modda olunup olunmadığını döner.
     */
    public static function isTesting(): bool
    {
        return self::$fake 
            || defined('PHPUNIT_RUNNING') 
            || (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing')
            || (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'testing');
    }

    /**
     * Mailer'ı test/sahte moda geçirir ve hafızayı temizler.
     */
    public static function fake(): void
    {
        self::$fake = true;
        self::$sentMails = [];
    }

    /**
     * Sahte moddan çıkarır.
     */
    public static function restore(): void
    {
        self::$fake = false;
        self::$sentMails = [];
    }

    /**
     * Gönderilen (yakalanan) tüm e-postaları döner.
     */
    public static function getSentMails(): array
    {
        return self::$sentMails;
    }

    /**
     * Gönderilen e-posta geçmişini temizler.
     */
    public static function clearSentMails(): void
    {
        self::$sentMails = [];
    }

    /**
     * Test modunda gönderilen e-postayı sahte listeye kaydeder.
     */
    public static function recordSentMailInFake(
        array $toAddresses,
        string $subject,
        string $body,
        string $altBody = '',
        array $attachments = [],
        string $mailerClass = ''
    ): void {
        if (self::isTesting()) {
            self::$sentMails[] = [
                'to'          => $toAddresses,
                'subject'     => $subject,
                'body'        => $body,
                'altBody'     => $altBody,
                'attachments' => $attachments,
                'mailer'      => $mailerClass ?: static::class,
            ];
        }
    }

    /**
     * Belirtilen filtreye uygun bir e-posta gönderilip gönderilmediğini doğrular.
     */
    public static function hasSent(string|callable $subjectOrCallback, ?string $toEmail = null): bool
    {
        foreach (self::$sentMails as $mail) {
            if (is_callable($subjectOrCallback)) {
                if ($subjectOrCallback($mail)) {
                    return true;
                }
                continue;
            }

            $subjectMatch = str_contains((string)($mail['subject'] ?? ''), $subjectOrCallback);
            if (!$subjectMatch) {
                continue;
            }

            if ($toEmail !== null) {
                $recipientFound = false;
                foreach ($mail['to'] ?? [] as $to) {
                    if (($to[0] ?? '') === $toEmail) {
                        $recipientFound = true;
                        break;
                    }
                }
                if (!$recipientFound) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * PHPMailer ayarlarını veritabanından çekerek yapılandırır.
     */
    protected function setup(): void
    {
        try {
            // Sunucu ayarları
            $this->mailer->isSMTP();
            $this->mailer->Host       = getSettingValue('smtp_host', 'mail', 'localhost');
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = getSettingValue('smtp_user', 'mail', '');
            $this->mailer->Password   = getSettingValue('smtp_pass', 'mail', '');
            $this->mailer->SMTPSecure = getSettingValue('smtp_secure', 'mail', PHPMailer::ENCRYPTION_STARTTLS);
            $this->mailer->Port       = getSettingValue('smtp_port', 'mail', 587);
            $this->mailer->CharSet    = 'UTF-8';

            // Gönderici bilgileri
            $fromEmail = getSettingValue('mail_from', 'mail', 'noreply@localhost');
            $fromName  = getSettingValue('mail_from_name', 'mail', 'Schedule Maker');
            $this->mailer->setFrom($fromEmail, $fromName);
            
            // HTML içeriği
            $this->mailer->isHTML(true);

        } catch (Exception $e) {
            Log::channel('mail')->error("Mailer yapılandırma hatası: {$e->getMessage()}", Log::context($this));
        }
    }

    /**
     * E-postayı doğrudan göndermek yerine mail kuyruğuna (MailQueue) ekler.
     * Uygulamadaki tüm e-posta gönderimleri merkezi kuyruk üzerinden yönetilir.
     * 
     * @return bool Kuyruğa ekleme başarılıysa true, aksi halde false.
     */
    public function send(): bool
    {
        // 1. Test veya sahte mod kontrolü (Hafızaya kaydet)
        if (self::isTesting()) {
            self::$sentMails[] = [
                'to'          => $this->mailer->getToAddresses(),
                'subject'     => $this->mailer->Subject ?? '',
                'body'        => $this->mailer->Body ?? '',
                'altBody'     => $this->mailer->AltBody ?? '',
                'attachments' => $this->mailer->getAttachments(),
                'mailer'      => static::class,
            ];
        }

        // 2. Merkezi mail kuyruğuna ekle
        $result = $this->enqueueFromMailer();
        $this->resetMailerState();
        return $result;
    }

    /**
     * PHPMailer nesnesindeki alıcı ve içerik bilgilerini MailQueue tablosuna aktarır.
     */
    protected function enqueueFromMailer(): bool
    {
        try {
            $toAddresses = $this->mailer->getToAddresses();
            if (empty($toAddresses)) {
                return false;
            }

            $subject = $this->mailer->Subject ?? '';
            $body = $this->mailer->Body ?? '';
            $altBody = $this->mailer->AltBody ?: strip_tags(str_replace(['<br>', '</li>', '</p>', '</tr>'], "\n", $body));

            $rawAttachments = $this->mailer->getAttachments();
            $attachments = [];
            if (!empty($rawAttachments)) {
                foreach ($rawAttachments as $att) {
                    $isString = $att[5] ?? false;
                    $filename = $att[2] ?: ($att[1] ?: 'attachment');
                    $type = $att[4] ?: 'application/octet-stream';

                    if ($isString) {
                        $content = base64_encode($att[0]);
                    } else {
                        $filePath = $att[0];
                        $content = (file_exists($filePath) && is_readable($filePath)) 
                            ? base64_encode(file_get_contents($filePath)) 
                            : '';
                    }

                    $attachments[] = [
                        'name'     => $filename,
                        'content'  => $content,
                        'encoding' => 'base64',
                        'type'     => $type
                    ];
                }
            }

            $queueService = new MailQueueService();
            $queuedCount = 0;

            foreach ($toAddresses as $to) {
                $email = $to[0] ?? '';
                $name = $to[1] ?? null;
                if (!empty($email)) {
                    $queueId = $queueService->enqueue(
                        toEmail: $email,
                        toName: $name,
                        subject: $subject,
                        body: $body,
                        altBody: $altBody,
                        attachments: $attachments
                    );
                    if ($queueId > 0) {
                        $queuedCount++;
                    }
                }
            }

            return $queuedCount > 0;
        } catch (\Throwable $e) {
            Log::channel('mail')->error("Mailer kuyruğa ekleme hatası: {$e->getMessage()}", Log::context($this));
            return false;
        }
    }

    /**
     * Mailer nesnesindeki alıcıları ve ekleri sıfırlar
     */
    public function resetMailerState(): void
    {
        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->clearCustomHeaders();
        $this->mailer->clearReplyTos();
        $this->mailer->clearCCs();
        $this->mailer->clearBCCs();
    }

    /**
     * E-postayı simülasyon modunda doğrudan Public/mail_log.html dosyasına görsel olarak kaydeder.
     */
    public static function logEmailToFileDirect(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $body,
        array $attachments = []
    ): bool {
        try {
            $recipientStr = htmlspecialchars($toName ? "$toName <$toEmail>" : $toEmail);
            $safeSubject = htmlspecialchars($subject ?: 'Konusuz E-posta');
            $dateStr = date('d.m.Y H:i:s');

            $attachmentBadges = '';
            if (!empty($attachments)) {
                foreach ($attachments as $att) {
                    $attName = htmlspecialchars($att['name'] ?? $att[2] ?? $att[1] ?? 'Ek Dosya');
                    $attachmentBadges .= "<span class='badge bg-secondary me-1'><i class='bi bi-paperclip'></i> {$attName}</span>";
                }
            } else {
                $attachmentBadges = "<span class='text-muted small'>Ek yok</span>";
            }

            $logFilePath = dirname(__DIR__, 2) . '/Public/mail_log.html';

            $newEntry = View::renderEmail('simulation/mail_card', [
                'subject'          => $safeSubject,
                'recipientStr'     => $recipientStr,
                'dateStr'          => $dateStr,
                'attachmentBadges' => $attachmentBadges,
                'body'             => $body
            ]);

            if (!file_exists($logFilePath) || filesize($logFilePath) === 0) {
                $initialTemplate = View::renderEmail('simulation/mail_log_page');
                @file_put_contents($logFilePath, $initialTemplate);
            }

            $existingContent = @file_get_contents($logFilePath) ?: '';
            if (str_contains($existingContent, '<!-- NEW_ENTRIES_HERE -->')) {
                $updatedContent = str_replace('<!-- NEW_ENTRIES_HERE -->', "<!-- NEW_ENTRIES_HERE -->\n" . $newEntry, $existingContent);
            } else {
                $updatedContent = str_replace('</div>' . "\n" . '    </div>' . "\n" . '    <script>', $newEntry . "\n        </div>\n    </div>\n    <script>", $existingContent);
            }

            if (@file_put_contents($logFilePath, $updatedContent) === false) {
                Log::channel('mail')->warning("Mail log dosyasına yazılamadı: {$logFilePath}");
            }

            Log::channel('mail')->info("E-posta simülasyon modunda yakalandı: {$recipientStr} - {$safeSubject}");
            return true;
        } catch (\Throwable $e) {
            Log::channel('mail')->error("E-posta loglama hatası: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * PHPMailer içeriğini dosyaya kaydeder.
     */
    protected function logEmailToFile(): bool
    {
        $toAddresses = $this->mailer->getToAddresses();
        $firstTo = $toAddresses[0] ?? ['', ''];
        return self::logEmailToFileDirect(
            $firstTo[0] ?? '',
            $firstTo[1] ?? null,
            $this->mailer->Subject ?? '',
            $this->mailer->Body ?? '',
            $this->mailer->getAttachments()
        );
    }
}
