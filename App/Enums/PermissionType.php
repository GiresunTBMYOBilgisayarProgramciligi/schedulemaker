<?php

namespace App\Enums;

enum PermissionType: string
{
    case LIST = 'list';
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
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
            self::LIST => 'Listele',
            self::VIEW => 'Görüntüle',
            self::CREATE => 'Oluştur',
            self::UPDATE => 'Güncelle',
            self::DELETE => 'Sil',
            self::MANAGE_LESSONS => 'Dersleri Yönet',
            self::MANAGE_BUILDINGS => 'Binaları ve Derslikleri Yönet',
            self::MANAGE_UNIT => 'Birimi Yönet',
            self::MANAGE_DEPARTMENT => 'Bölümü Yönet',
            self::MANAGE_PROGRAM => 'Programı Yönet',
            self::MANAGE_USERS => 'Kullanıcıları Yönet',
            self::MANAGE_SCHEDULE => 'Ders Programını Yönet',
        };
    }

    /**
     * Sadece sihirbazda listelenecek (kullanıcıya atanabilecek) özel yetkileri döndürür.
     * @return array
     */
    public static function getManageablePermissions(): array
    {
        return [
            self::MANAGE_LESSONS,
            self::MANAGE_BUILDINGS,
            self::MANAGE_UNIT,
            self::MANAGE_DEPARTMENT,
            self::MANAGE_PROGRAM,
            self::MANAGE_USERS,
            self::MANAGE_SCHEDULE,
        ];
    }
}
