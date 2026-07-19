<?php

namespace App\Enums;

use App\Core\Gate;

/**
 * Kullanıcı rollerini temsil eden Backed Enum.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case SubManager = 'submanager';
    case Secretary = 'secretary';
    case DepartmentHead = 'department_head';
    case ResearchAssistant = 'research_assistant';
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
            self::Secretary => 'Sekreter',
            self::DepartmentHead => 'Bölüm Başkanı',
            self::ResearchAssistant => 'Araştırma Görevlisi',
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
        $roles = [self::User, self::Lecturer, self::ResearchAssistant];
        
        if (Gate::allowsRole("admin")) {
            $roles = array_merge(
                $roles,
                [self::DepartmentHead, self::Secretary, self::SubManager, self::Manager, self::Admin]
            );
        } elseif (Gate::allowsRole("manager")) {
            $roles = array_merge(
                $roles,
                [self::DepartmentHead, self::Secretary, self::SubManager, self::Manager]
            );
        } elseif (Gate::allowsRole("submanager")) {
            $roles = array_merge(
                $roles,
                [self::DepartmentHead, self::Secretary]
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
