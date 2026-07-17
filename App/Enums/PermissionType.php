<?php

namespace App\Enums;

enum PermissionType: string
{
    case MANAGE_LESSONS = 'manage_lessons';
    case MANAGE_BUILDINGS = 'manage_buildings';
    case MANAGE_UNIT = 'manage_unit';
    case MANAGE_DEPARTMENT = 'manage_department';
    case MANAGE_PROGRAM = 'manage_program';
    case MANAGE_USERS = 'manage_users';
    case MANAGE_SCHEDULE = 'manage_schedule';

    /**
     * Enum değerine ait okunabilir etiketi döndürür.
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::MANAGE_LESSONS => 'Dersleri Yönet',
            self::MANAGE_BUILDINGS => 'Binaları ve Derslikleri Yönet',
            self::MANAGE_UNIT => 'Birimi Yönet',
            self::MANAGE_DEPARTMENT => 'Bölümü Yönet',
            self::MANAGE_PROGRAM => 'Programı Yönet',
            self::MANAGE_USERS => 'Kullanıcıları Yönet',
            self::MANAGE_SCHEDULE => 'Ders Programını Yönet',
        };
    }
}
