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
     * Bu yetkinin hangi seviyelerde (scope) verilebileceğini döndürür.
     * @return array
     */
    public function getAllowedScopes(): array
    {
        return match ($this) {
            self::MANAGE_UNIT => ['units'],
            self::MANAGE_DEPARTMENT => ['units', 'departments'],
            self::MANAGE_PROGRAM, 
            self::MANAGE_SCHEDULE, 
            self::MANAGE_LESSONS, 
            self::MANAGE_USERS => ['units', 'departments', 'programs'],
            self::MANAGE_BUILDINGS => ['units'],
            default => []
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
