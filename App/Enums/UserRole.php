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
    case PayrollOfficer = 'payroll_officer';
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
            self::PayrollOfficer => 'Mutemet',
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
                [self::DepartmentHead, self::PayrollOfficer, self::Secretary, self::SubManager, self::Manager, self::Admin]
            );
        } elseif (Gate::allowsRole("manager")) {
            $roles = array_merge(
                $roles,
                [self::DepartmentHead, self::PayrollOfficer, self::Secretary, self::SubManager, self::Manager]
            );
        } elseif (Gate::allowsRole("submanager")) {
            $roles = array_merge(
                $roles,
                [self::DepartmentHead, self::PayrollOfficer, self::Secretary]
            );
        }
        
        return $roles;
    }

    public function isAcademic(): bool
    {
        return in_array($this->value, self::getAcademicRoles(), true);
    }

    public function isAdministrative(): bool
    {
        return !in_array($this->value, self::getAcademicRoles(), true);
    }

    /**
     * @return string[]
     */
    public static function getAcademicRoles(): array
    {
        return [self::Manager->value, self::SubManager->value, self::DepartmentHead->value, self::Lecturer->value, self::ResearchAssistant->value];
    }

    /**
     * @return string[]
     */
    public static function getAdministrativeRoles(): array
    {
        return [self::Admin->value, self::Secretary->value, self::PayrollOfficer->value, self::User->value];
    }

    public static function getAcademicRoleValues(): array
    {
        return self::getAcademicRoles();
    }

    public static function getAdministrativeRoleValues(): array
    {
        return self::getAdministrativeRoles();
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
