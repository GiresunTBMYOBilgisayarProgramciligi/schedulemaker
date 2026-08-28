<?php

namespace App\Models;

use App\Core\Model;
use App\Enums\MailQueueStatus;

class MailQueue extends Model
{
    protected string $table_name = "mail_queue";

    public ?int $id = null;
    public ?string $to_email = null;
    public ?string $to_name = null;
    public ?string $subject = null;
    public ?string $body = null;
    public ?string $alt_body = null;
    public ?string $attachments = null;
    public ?string $status = 'pending';
    public ?int $attempts = 0;
    public ?string $error_message = null;
    public ?string $created_at = null;
    public ?string $sent_at = null;

    public function getLabel(): string
    {
        return 'e-posta kuyruğu';
    }

    public function getLogDetail(): string
    {
        return ($this->to_email ?? '') . ' - ' . ($this->subject ?? '');
    }

    public function getStatusEnum(): ?MailQueueStatus
    {
        return MailQueueStatus::tryFrom((string)$this->status);
    }
}
