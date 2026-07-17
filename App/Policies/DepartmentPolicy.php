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
        return $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'department_head';
    }

    /**
     * Bölüm detayını görme yetkisi
     */
    public function view(User $user, Department $department): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Bölüm başkanı sadece kendi bölümünü görebilir
        if ($user->role === 'department_head') {
            return $user->department_id === $department->id;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_DEPARTMENT->value, $department);
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

        // Bölüm başkanı sadece kendi bölümünü güncelleyebilir
        if ($user->role === 'department_head') {
            return $user->department_id === $department->id;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_DEPARTMENT->value, $department);
    }

    /**
     * Bölüm silme yetkisi
     */
    public function delete(User $user, Department $department): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_DEPARTMENT->value, $department);
    }
}
