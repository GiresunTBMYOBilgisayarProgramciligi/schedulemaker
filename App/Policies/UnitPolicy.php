<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Unit;
use App\Core\Gate;

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

        // Özel yetki (Settings tablosundan)
        if (Gate::canAccessUnit($unit->id)) {
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

        // Özel yetki (Settings tablosundan)
        if (Gate::canAccessUnit($unit->id)) {
            return true;
        }

        return false;
    }

    /**
     * Birim silme yetkisi
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $user->role === 'admin' || $user->role === 'manager';
    }
}
