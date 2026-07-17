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
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'department_head';
    }

    /**
     * Profil görüntüleme yetkisi
     */
    public function view(User $user, User $targetUser): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Kendi profili
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Kendi bölümündeki kullanıcılar (Bölüm Başkanı için)
        if ($user->role === 'department_head') {
            return $user->department_id === $targetUser->department_id;
        }

        // Özel yetki (Settings tablosundan JSON olarak)
        $perms = Gate::getUserPermissions($user->id);
        if (isset($targetUser->department_id)) {
            $deptPerms = $perms['departments'][$targetUser->department_id] ?? [];
            if (in_array(PermissionType::VIEW->value, $deptPerms) || in_array(PermissionType::MANAGE_USERS->value, $deptPerms)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Yeni kullanıcı ekleme yetkisi
     */
    public function create(User $user, ?array $userData = null): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Bölüm başkanı kendi bölümüne ekleyebilir
        if ($user->role === 'department_head' && isset($userData['department_id'])) {
            return $user->department_id == $userData['department_id'];
        }

        if (isset($userData['department_id'])) {
            $perms = Gate::getUserPermissions($user->id);
            if (in_array(PermissionType::MANAGE_USERS->value, $perms['departments'][$userData['department_id']] ?? [])) {
                return true;
            }
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

        // Kendi bilgileri
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Kendi bölümündeki birini güncelleme (Bölüm Başkanı)
        if ($user->role === 'department_head') {
            return $user->department_id === $targetUser->department_id;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (isset($targetUser->department_id)) {
            if (in_array(PermissionType::MANAGE_USERS->value, $perms['departments'][$targetUser->department_id] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kullanıcı silme yetkisi
     */
    public function delete(User $user, User $targetUser): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (isset($targetUser->department_id)) {
            if (in_array(PermissionType::MANAGE_USERS->value, $perms['departments'][$targetUser->department_id] ?? []) && in_array(PermissionType::DELETE->value, $perms['departments'][$targetUser->department_id] ?? [])) {
                return true;
            }
        }

        return false;
    }
}
