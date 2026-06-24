<?php

namespace App\Enums;

/**
 * Kullanıcı rollerini temsil eden Backed Enum.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case SubManager = 'submanager';
    case DepartmentHead = 'department_head';
    case Lecturer = 'lecturer';
    case User = 'user';

    /**
     * Arayüzde (Formlarda) gösterilecek Türkçe etiketleri döndürür.
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Yönetici',
            self::Manager => 'Müdür',
            self::SubManager => 'Müdür Yardımcısı',
            self::DepartmentHead => 'Bölüm Başkanı',
            self::Lecturer => 'Akademisyen',
            self::User => 'Kullanıcı',
        };
    }
}
