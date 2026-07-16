<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Building;

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
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
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
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Bina silme yetkisi
     */
    public function delete(User $user, Building $building): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
    }
}
