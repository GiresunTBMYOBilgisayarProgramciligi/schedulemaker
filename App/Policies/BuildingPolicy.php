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
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Bina detayını görme yetkisi
     */
    public function view(User $user, Building $building): bool
    {
        if ($user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::MANAGE_BUILDINGS->value, $perms['buildings'][$building->id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Yeni bina ekleme yetkisi
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Bina güncelleme yetkisi
     */
    public function update(User $user, Building $building): bool
    {
        if ($user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::MANAGE_BUILDINGS->value, $perms['buildings'][$building->id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Bina silme yetkisi
     */
    public function delete(User $user, Building $building): bool
    {
        if ($user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::MANAGE_BUILDINGS->value, $perms['buildings'][$building->id] ?? [])) {
            return true;
        }

        return false;
    }
}
