<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Department;
use App\Core\Gate;
use App\Enums\PermissionType;

use App\Enums\UserRole;

class DepartmentPolicy extends BasePolicy
{
    /**
     * Bölüm listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $this->hasRole($user, UserRole::DepartmentHead) || 
               $this->hasAnyPermission($user, PermissionType::MANAGE_DEPARTMENT->value);
    }

    /**
     * Bölüm detayını görme yetkisi
     */
    public function view(User $user, Department $department): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            if (!is_null($user->unit_id) && $user->unit_id == $department->unit_id) {
                return true;
            }
        }

        // Kullanıcı kendi bölümünü görebilir
        if ($user->department_id === $department->id) {
            return true;
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_DEPARTMENT->value, $department);
    }

    /**
     * Yeni bölüm ekleme yetkisi
     */
    public function create(User $user, $model = null, $departmentData = null): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            if (isset($departmentData->unit_id)) {
                return !is_null($user->unit_id) && $user->unit_id == $departmentData->unit_id;
            }
            return !is_null($user->unit_id);
        }

        // Eğer $departmentData ile unit_id geldiyse, o birim için MANAGE_DEPARTMENT yetkisi var mı?
        if (isset($departmentData->unit_id)) {
            return $this->hasCascadePermission($user, PermissionType::MANAGE_DEPARTMENT->value, null, ['unit_id' => $departmentData->unit_id]);
        }

        // Genel yetki kontrolü
        return $this->hasCascadePermission($user, PermissionType::MANAGE_DEPARTMENT->value);
    }

    /**
     * Bölüm güncelleme yetkisi
     */
    public function update(User $user, Department $department): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            if (!is_null($user->unit_id) && $user->unit_id == $department->unit_id) {
                return true;
            }
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_DEPARTMENT->value, $department);
    }

    /**
     * Bölüm silme yetkisi
     */
    public function delete(User $user, Department $department): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            if (!is_null($user->unit_id) && $user->unit_id == $department->unit_id) {
                return true;
            }
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_DEPARTMENT->value, $department);
    }

    /**
     * Bölümün ders/sınav programını yönetme yetkisi
     */
    public function manage_schedule(User $user, Department $department): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            if (!is_null($user->unit_id) && $user->unit_id == $department->unit_id) {
                return true;
            }
        }

        if ($this->hasExactRole($user, UserRole::DepartmentHead)) {
            if ($user->department_id === $department->id || $department->chairperson_id === $user->id) {
                return true;
            }
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, $department);
    }

    /**
     * Bölümün derslerini yönetme yetkisi
     */
    public function manage_lessons(User $user, Department $department): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            if (!is_null($user->unit_id) && $user->unit_id == $department->unit_id) {
                return true;
            }
        }

        if ($this->hasExactRole($user, UserRole::DepartmentHead)) {
            if ($user->department_id === $department->id || $department->chairperson_id === $user->id) {
                return true;
            }
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_LESSONS->value, $department);
    }
}
