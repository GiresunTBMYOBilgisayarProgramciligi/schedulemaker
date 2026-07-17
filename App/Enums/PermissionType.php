<?php

namespace App\Enums;

enum PermissionType: string
{
    case VIEW = 'view';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case MANAGE_USERS = 'manage_users';
    case MANAGE_SCHEDULE = 'manage_schedule';
    case MANAGE_LESSONS = 'manage_lessons';
    case MANAGE_BUILDINGS = 'manage_buildings';
    case MANAGE_UNIT = 'manage_unit';
    case MANAGE_DEPARTMENT = 'manage_department';
    case MANAGE_PROGRAM = 'manage_program';

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'Görüntüleme',
            self::UPDATE => 'Güncelleme',
            self::DELETE => 'Silme',
            self::MANAGE_USERS => 'Kullanıcı Yönetimi',
            self::MANAGE_SCHEDULE => 'Ders/Sınav Programı Yönetimi',
            self::MANAGE_LESSONS => 'Ders Yönetimi',
            self::MANAGE_BUILDINGS => 'Bina ve Derslik Yönetimi',
            self::MANAGE_UNIT => 'Birim Yönetimi',
            self::MANAGE_DEPARTMENT => 'Bölüm Yönetimi',
            self::MANAGE_PROGRAM => 'Program Yönetimi',
        };
    }
}
