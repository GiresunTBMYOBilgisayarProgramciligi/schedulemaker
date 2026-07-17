<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Department;
use App\Core\Gate;
use App\Enums\PermissionType;

class UserPolicy extends BasePolicy
{
    /**
     * Kullanıcı listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'department_head';
    }

    /**
     * Kullanıcı detayını görme yetkisi
     */
    public function view(User $user, User $targetUser): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Kullanıcı kendini görebilir
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Bölüm başkanı sadece kendi bölümündeki kullanıcıları görebilir
        if ($user->role === 'department_head') {
            return $user->department_id === $targetUser->department_id;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_USERS->value, null, ['department_id' => $targetUser->department_id, 'program_id' => $targetUser->program_id]);
    }

    /**
     * Yeni kullanıcı ekleme yetkisi
     */
    public function create(User $user, ?array $userData = null): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        if ($user->role === 'department_head' && isset($userData['department_id'])) {
             return $user->department_id == $userData['department_id'];
        }

        if (isset($userData['department_id']) || isset($userData['program_id'])) {
            return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_USERS->value, null, [
                'department_id' => $userData['department_id'] ?? null,
                'program_id' => $userData['program_id'] ?? null
            ]);
        }

        return false;
    }

    /**
     * Kullanıcı güncelleme yetkisi
     */
    public function update(User $user, User $targetUser): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Kullanıcı kendi profilini güncelleyebilir
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Bölüm başkanı sadece kendi bölümündeki kullanıcıları güncelleyebilir
        if ($user->role === 'department_head') {
            return $user->department_id === $targetUser->department_id;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_USERS->value, null, ['department_id' => $targetUser->department_id, 'program_id' => $targetUser->program_id]);
    }

    /**
     * Kullanıcı silme yetkisi
     */
    public function delete(User $user, User $targetUser): bool
    {
        if ($user->role === 'manager') {
            return true;
        }

        if ($user->role === 'department_head') {
             return $user->department_id === $targetUser->department_id;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_USERS->value, null, ['department_id' => $targetUser->department_id, 'program_id' => $targetUser->program_id]);
    }
}
