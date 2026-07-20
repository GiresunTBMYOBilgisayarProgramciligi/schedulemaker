<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Unit;
use App\Core\Gate;
use App\Enums\PermissionType;

class UnitPolicy extends BasePolicy
{
    /**
     * Birim listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Birim detayını görme yetkisi
     */
    public function view(User $user, Unit $unit): bool
    {
        if ($user->role === 'manager' || ($user->role === 'submanager' && $user->unit_id == $unit->id)) {
            return true;
        }

        if ($user->unit_id === $unit->id) {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_UNIT->value, $unit);
    }

    /**
     * Yeni birim ekleme yetkisi
     */
    public function create(User $user): bool
    {
        return $user->role === 'manager';
    }

    /**
     * Birim güncelleme yetkisi
     */
    public function update(User $user, Unit $unit): bool
    {
        if ($user->role === 'manager') {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_UNIT->value, $unit);
    }

    /**
     * Birim silme yetkisi
     */
    public function delete(User $user, Unit $unit): bool
    {
        if ($user->role === 'manager') {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_UNIT->value, $unit);
    }
}
