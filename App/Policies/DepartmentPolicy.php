<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Department;
use App\Core\Gate;
use App\Enums\PermissionType;

class DepartmentPolicy extends BasePolicy
{
    /**
     * Bölüm listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'department_head';
    }

    /**
     * Bölüm detayını görme yetkisi
     */
    public function view(User $user, Department $department): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Bölüm Başkanı ise
        if ($user->id === $department->chairperson_id) {
            return true;
        }

        // Bölüme kayıtlı kullanıcı ise
        if ($user->department_id === $department->id) {
            return true;
        }

        // Özel yetki (Settings tablosundan)
        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::VIEW->value, $perms['departments'][$department->id] ?? []) || in_array(PermissionType::MANAGE_DEPARTMENT->value, $perms['departments'][$department->id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Yeni bölüm ekleme yetkisi
     */
    public function create(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Bölüm güncelleme yetkisi
     */
    public function update(User $user, Department $department): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }
        
        if ($user->id === $department->chairperson_id) {
            return true;
        }

        // Özel yetki (Settings tablosundan)
        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::UPDATE->value, $perms['departments'][$department->id] ?? []) || in_array(PermissionType::MANAGE_DEPARTMENT->value, $perms['departments'][$department->id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Bölüm silme yetkisi
     */
    public function delete(User $user, Department $department): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::DELETE->value, $perms['departments'][$department->id] ?? []) || in_array(PermissionType::MANAGE_DEPARTMENT->value, $perms['departments'][$department->id] ?? [])) {
            return true;
        }

        return false;
    }
}
