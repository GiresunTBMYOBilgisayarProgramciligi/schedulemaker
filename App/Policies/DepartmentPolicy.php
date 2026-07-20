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
            if (!is_null($user->unit_id) && $user->unit_id == $department->unit_id) {
                return true;
            }
        }

        // Kullanıcı kendi bölümünü görebilir
        if ($user->department_id === $department->id) {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_DEPARTMENT->value, $department);
    }

    /**
     * Yeni bölüm ekleme yetkisi
     */
    public function create(User $user, $departmentData = null): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            if (isset($departmentData->unit_id)) {
                return !is_null($user->unit_id) && $user->unit_id == $departmentData->unit_id;
            }
            return !is_null($user->unit_id);
        }

        // Eğer $departmentData ile unit_id geldiyse, o birim için MANAGE_DEPARTMENT yetkisi var mı?
        if (isset($departmentData->unit_id)) {
            return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_DEPARTMENT->value, null, ['unit_id' => $departmentData->unit_id]);
        }

        // Genel yetki kontrolü
        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_DEPARTMENT->value);
    }

    /**
     * Bölüm güncelleme yetkisi
     */
    public function update(User $user, Department $department): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            if (!is_null($user->unit_id) && $user->unit_id == $department->unit_id) {
                return true;
            }
        }



        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_DEPARTMENT->value, $department);
    }

    /**
     * Bölüm silme yetkisi
     */
    public function delete(User $user, Department $department): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            if (!is_null($user->unit_id) && $user->unit_id == $department->unit_id) {
                return true;
            }
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_DEPARTMENT->value, $department);
    }
}
