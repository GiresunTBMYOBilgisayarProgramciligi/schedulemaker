<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Classroom;

class ClassroomPolicy extends BasePolicy
{
    /**
     * Derslik listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Derslik detayını görme yetkisi
     */
    public function view(User $user, Classroom $classroom): bool
    {
        // canUserDoAction her zaman true döndüğü için mevcut mantığı koruyoruz
        return true;
    }

    /**
     * Yeni derslik ekleme yetkisi
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Derslik güncelleme yetkisi
     */
    public function update(User $user, Classroom $classroom): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Derslik silme yetkisi
     */
    public function delete(User $user, Classroom $classroom): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
    }
}
