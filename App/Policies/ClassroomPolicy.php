<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Classroom;
use App\Enums\PermissionType;

use App\Enums\UserRole;

class ClassroomPolicy extends BasePolicy
{
    /**
     * Derslik listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        if ($this->hasRole($user, UserRole::Secretary)) {
            return true;
        }
        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value);
    }

    /**
     * Derslik detayını görme yetkisi
     */
    public function view(User $user, Classroom $classroom): bool
    {
        $unit = $classroom->getUnit();
        if ($this->hasRole($user, UserRole::Secretary) && !is_null($user->unit_id) && $user->unit_id === ($unit ? $unit->id : null)) {
            return true;
        }
        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value, $unit);
    }

    /**
     * Yeni derslik ekleme yetkisi
     */
    public function create(User $user, $model = null, $classroom = null): bool
    {
        if ($classroom) {
            $unit = $classroom->getUnit();
            if ($this->hasRole($user, UserRole::Secretary) && !is_null($user->unit_id) && $user->unit_id === ($unit ? $unit->id : null)) {
                return true;
            }
            return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value, $unit);
        }
        
        if ($this->hasRole($user, UserRole::Secretary)) {
            return true;
        }
        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value);
    }

    /**
     * Derslik güncelleme yetkisi
     */
    public function update(User $user, Classroom $classroom): bool
    {
        $unit = $classroom->getUnit();
        if ($this->hasRole($user, UserRole::Secretary) && !is_null($user->unit_id) && $user->unit_id === ($unit ? $unit->id : null)) {
            return true;
        }
        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value, $unit);
    }

    /**
     * Derslik silme yetkisi
     */
    public function delete(User $user, Classroom $classroom): bool
    {
        $unit = $classroom->getUnit();
        if ($this->hasRole($user, UserRole::Secretary) && !is_null($user->unit_id) && $user->unit_id === ($unit ? $unit->id : null)) {
            return true;
        }
        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value, $unit);
    }
}
