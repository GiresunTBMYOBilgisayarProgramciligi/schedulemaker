<?php

namespace App\Enums;

enum MailQueueStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Sent       = 'sent';
    case Failed     = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending    => 'Bekliyor',
            self::Processing => 'İşleniyor',
            self::Sent       => 'Gönderildi',
            self::Failed     => 'Başarısız',
        };
    }
}
