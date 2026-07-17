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
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Birim detayını görme yetkisi
     */
    public function view(User $user, Unit $unit): bool
    {
        if ($user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Özel yetki (Settings tablosundan JSON olarak)
        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::VIEW->value, $perms['units'][$unit->id] ?? []) || in_array(PermissionType::MANAGE_UNIT->value, $perms['units'][$unit->id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Yeni birim ekleme yetkisi
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'manager';
    }

    /**
     * Birim güncelleme yetkisi
     */
    public function update(User $user, Unit $unit): bool
    {
        if ($user->role === 'admin' || $user->role === 'manager') {
            return true;
        }

        // Özel yetki (Settings tablosundan JSON olarak)
        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::UPDATE->value, $perms['units'][$unit->id] ?? []) || in_array(PermissionType::MANAGE_UNIT->value, $perms['units'][$unit->id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Birim silme yetkisi
     */
    public function delete(User $user, Unit $unit): bool
    {
        if ($user->role === 'admin' || $user->role === 'manager') {
            return true;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::DELETE->value, $perms['units'][$unit->id] ?? []) || in_array(PermissionType::MANAGE_UNIT->value, $perms['units'][$unit->id] ?? [])) {
            return true;
        }

        return false;
    }
}
