<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Classroom;
use App\Core\Gate;
use App\Enums\PermissionType;

class ClassroomPolicy extends BasePolicy
{
    /**
     * Derslik listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Derslik detayını görme yetkisi
     */
    public function view(User $user, Classroom $classroom): bool
    {
        return true;
    }

    /**
     * Yeni derslik ekleme yetkisi
     */
    public function create(User $user): bool
    {
        // Yeni derslik eklerken bina ID'si gönderilmişse o binada yetkisi var mı kontrol edilir
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }
        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_BUILDINGS->value);
    }

    /**
     * Derslik güncelleme yetkisi
     */
    public function update(User $user, Classroom $classroom): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }
        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_BUILDINGS->value);
    }

    /**
     * Derslik silme yetkisi
     */
    public function delete(User $user, Classroom $classroom): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }
        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_BUILDINGS->value);
    }
}
