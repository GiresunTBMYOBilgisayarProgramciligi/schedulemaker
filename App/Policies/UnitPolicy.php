<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Unit;

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
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
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
        return $user->role === 'admin' || $user->role === 'manager';
    }

    /**
     * Birim silme yetkisi
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $user->role === 'admin' || $user->role === 'manager';
    }
}
