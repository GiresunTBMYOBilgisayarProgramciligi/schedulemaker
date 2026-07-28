<?php

namespace App\Enums;

enum ScheduleNoteStatus: string
{
    case PENDING = 'pending';
    case READ = 'read';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

    /**
     * Etiketi döndürür.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Beklemede',
            self::READ => 'Görüldü',
            self::COMPLETED => 'Gereği Yapıldı',
            self::REJECTED => 'Reddedildi',
        };
    }

    /**
     * AdminLTE rozet (badge) sınıfını döndürür.
     */
    public function getBadgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'text-bg-secondary',
            self::READ => 'text-bg-info',
            self::COMPLETED => 'text-bg-success',
            self::REJECTED => 'text-bg-danger',
        };
    }
}
