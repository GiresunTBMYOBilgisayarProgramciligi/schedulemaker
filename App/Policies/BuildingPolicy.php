<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Building;
use App\Core\Gate;
use App\Enums\PermissionType;

class BuildingPolicy extends BasePolicy
{
    /**
     * Bina listesini görme yetkisi
     */
    public function list(User $user): bool
    {

        if (Gate::allowsRole('secretary')) {
            return true;
        }
        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_BUILDINGS->value);
    }

    /**
     * Bina detayını görme yetkisi
     */
    public function view(User $user, Building $building): bool
    {

        if (Gate::allowsRole('secretary') && $user->unit_id === $building->unit_id) {
            return true;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::MANAGE_BUILDINGS->value, $perms['buildings'][$building->id] ?? [])) {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_BUILDINGS->value, $building);
    }

    /**
     * Yeni bina ekleme yetkisi
     */
    public function create(User $user, Building $building = null): bool
    {

        if ($building && Gate::allowsRole('secretary') && $user->unit_id === $building->unit_id) {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_BUILDINGS->value, $building);
    }

    /**
     * Bina güncelleme yetkisi
     */
    public function update(User $user, Building $building): bool
    {
        if (Gate::allowsRole('secretary') && $user->unit_id === $building->unit_id) {
            return true;
        }
        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_BUILDINGS->value, $building);
    }

    /**
     * Bina silme yetkisi
     */
    public function delete(User $user, Building $building): bool
    {
        if (Gate::allowsRole('secretary') && $user->unit_id === $building->unit_id) {
            return true;
        }
        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_BUILDINGS->value, $building);
    }
}
