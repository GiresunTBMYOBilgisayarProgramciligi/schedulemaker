<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Building;
use App\Enums\PermissionType;

use App\Enums\UserRole;

class BuildingPolicy extends BasePolicy
{
    /**
     * Bina listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        if ($this->hasRole($user, UserRole::Secretary)) {
            return true;
        }
        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value);
    }

    /**
     * Bina detayını görme yetkisi
     */
    public function view(User $user, Building $building): bool
    {
        if ($this->hasRole($user, UserRole::Secretary) && !is_null($user->unit_id) && $user->unit_id === $building->unit_id) {
            return true;
        }

        $perms = $this->getUserPermissions($user->id);
        if (in_array(PermissionType::MANAGE_BUILDINGS->value, $perms['buildings'][$building->id] ?? [])) {
            return true;
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value, $building);
    }

    /**
     * Yeni bina ekleme yetkisi
     */
    public function create(User $user, $model = null, $building = null): bool
    {
        if ($building && $this->hasRole($user, UserRole::Secretary) && !is_null($user->unit_id) && $user->unit_id === $building->unit_id) {
            return true;
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value, $building);
    }

    /**
     * Bina güncelleme yetkisi
     */
    public function update(User $user, Building $building): bool
    {
        if ($this->hasRole($user, UserRole::Secretary) && !is_null($user->unit_id) && $user->unit_id === $building->unit_id) {
            return true;
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value, $building);
    }

    /**
     * Bina silme yetkisi
     */
    public function delete(User $user, Building $building): bool
    {
        if ($this->hasRole($user, UserRole::Secretary) && !is_null($user->unit_id) && $user->unit_id === $building->unit_id) {
            return true;
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_BUILDINGS->value, $building);
    }
}
