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

    /**
     * Oturum açmış kullanıcının yetkisine göre atanabilir rolleri döndürür.
     * @return array<self>
     */
    public static function getAssignableRoles(): array
    {
        $roles = [self::User, self::Lecturer];
        
        if (\App\Core\Gate::allowsRole("admin")) {
            $roles = array_merge(
                $roles,
                [self::DepartmentHead, self::SubManager, self::Manager, self::Admin]
            );
        } elseif (\App\Core\Gate::allowsRole("manager")) {
            $roles = array_merge(
                $roles,
                [self::DepartmentHead, self::SubManager, self::Manager]
            );
        } elseif (\App\Core\Gate::allowsRole("submanager")) {
            $roles = array_merge(
                $roles,
                [self::DepartmentHead]
            );
        }
        
        return $roles;
    }

    /**
     * Label üzerinden Enum örneğini döndürür.
     * @param string $label
     * @return self|null
     */
    public static function fromLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getLabel() === $label) {
                return $case;
            }
        }
        return null;
    }
}
