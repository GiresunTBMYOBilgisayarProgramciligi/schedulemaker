<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Program;
use App\Models\Department;
use App\Enums\PermissionType;

use App\Enums\UserRole;

class ProgramPolicy extends BasePolicy
{
    /**
     * Program listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $this->hasRole($user, UserRole::DepartmentHead) || 
               $this->hasAnyPermission($user, PermissionType::MANAGE_PROGRAM->value);
    }

    /**
     * Program detayını görme yetkisi
     */
    public function view(User $user, Program $program): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            $programUnitId = $program->department ? $program->department->unit_id : (new Department())->find($program->department_id)?->unit_id;
            if (!is_null($user->unit_id) && $user->unit_id == $programUnitId) {
                return true;
            }
        }

        // Programın bağlı olduğu bölümün başkanı
        if ($this->hasExactRole($user, UserRole::DepartmentHead)) {
            return $user->department_id === $program->department_id;
        }

        // Sisteme kayıtlı olduğu program ise (Akademisyen veya Öğrenci)
        if ($user->program_id === $program->id) {
            return true;
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_PROGRAM->value, $program);
    }

    /**
     * Yeni program ekleme yetkisi
     */
    public function create(User $user, $model = null, $programData = null): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            return true;
        }

        if (isset($programData->department_id)) {
            return $this->hasCascadePermission($user, PermissionType::MANAGE_PROGRAM->value, null, ['department_id' => $programData->department_id]);
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_PROGRAM->value);
    }

    /**
     * Program güncelleme yetkisi
     */
    public function update(User $user, Program $program): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            $programUnitId = $program->department ? $program->department->unit_id : (new Department())->find($program->department_id)?->unit_id;
            if (!is_null($user->unit_id) && $user->unit_id == $programUnitId) {
                return true;
            }
        }

        // Bölüm başkanı kendi programlarını güncelleyebilir
        if ($this->hasExactRole($user, UserRole::DepartmentHead)) {
            return $user->department_id === $program->department_id;
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_PROGRAM->value, $program);
    }

    /**
     * Program silme yetkisi
     */
    public function delete(User $user, Program $program): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            $programUnitId = $program->department ? $program->department->unit_id : (new Department())->find($program->department_id)?->unit_id;
            if (!is_null($user->unit_id) && $user->unit_id == $programUnitId) {
                return true;
            }
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_PROGRAM->value, $program);
    }

    /**
     * Programın ders/sınav programını yönetme yetkisi
     */
    public function manage_schedule(User $user, Program $program): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            $programUnitId = $program->department ? $program->department->unit_id : (new Department())->find($program->department_id)?->unit_id;
            if (!is_null($user->unit_id) && $user->unit_id == $programUnitId) {
                return true;
            }
        }

        if ($this->hasExactRole($user, UserRole::DepartmentHead)) {
            return $user->department_id === $program->department_id;
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, $program);
    }

    /**
     * Programın derslerini yönetme yetkisi
     */
    public function manage_lessons(User $user, Program $program): bool
    {
        if ($this->hasRole($user, UserRole::SubManager)) {
            $programUnitId = $program->department ? $program->department->unit_id : (new Department())->find($program->department_id)?->unit_id;
            if (!is_null($user->unit_id) && $user->unit_id == $programUnitId) {
                return true;
            }
        }

        if ($this->hasExactRole($user, UserRole::DepartmentHead)) {
            return $user->department_id === $program->department_id;
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_LESSONS->value, $program);
    }
}
