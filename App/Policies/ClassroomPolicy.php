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
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager';
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
        if ($user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        return false; // Derslik ekleme binaya özeldir, request'ten bina id gelmeli. Şimdilik adminler yapabilir.
    }

    /**
     * Derslik güncelleme yetkisi
     */
    public function update(User $user, Classroom $classroom): bool
    {
        if ($user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::MANAGE_BUILDINGS->value, $perms['buildings'][$classroom->building_id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Derslik silme yetkisi
     */
    public function delete(User $user, Classroom $classroom): bool
    {
        if ($user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::MANAGE_BUILDINGS->value, $perms['buildings'][$classroom->building_id] ?? [])) {
            return true;
        }

        return false;
    }
}
