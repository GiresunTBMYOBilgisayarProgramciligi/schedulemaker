<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\PermissionType;

class UserPolicy extends BasePolicy
{
    private function checkPermissionWithAffiliations(User $user, string $permission, User $targetUser): bool
    {
        if ($this->hasCascadePermission($user, $permission, null, [
            'unit_id' => $targetUser->unit_id,
            'department_id' => $targetUser->department_id,
            'program_id' => $targetUser->program_id
        ])) {
            return true;
        }

        foreach ($targetUser->getAffiliations() as $aff) {
            if ($this->hasCascadePermission($user, $permission, null, [
                'unit_id' => $aff->unit_id,
                'department_id' => $aff->department_id,
                'program_id' => $aff->program_id
            ])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Kullanıcı listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'manager' || 
               $user->role === 'submanager' || 
               $user->role === 'department_head' ||
               $this->hasAnyPermission($user, PermissionType::MANAGE_USERS->value);
    }

    /**
     * Kullanıcı detayını görme yetkisi
     */
    public function view(User $user, User $targetUser): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            if (!is_null($user->unit_id)) {
                if ($user->unit_id == $targetUser->unit_id || empty($targetUser->unit_id)) return true;
                foreach ($targetUser->getAffiliations() as $aff) {
                    if ($aff->unit_id == $user->unit_id) return true;
                }
            }
        }

        // Kullanıcı kendini görebilir
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Bölüm başkanı sadece kendi bölümündeki kullanıcıları veya atanmamış olanları görebilir
        if ($user->role === 'department_head') {
            if ($user->department_id === $targetUser->department_id || empty($targetUser->department_id)) return true;
            foreach ($targetUser->getAffiliations() as $aff) {
                if ($aff->department_id === $user->department_id) return true;
            }
            return false;
        }

        // Kullanıcının targetUser'ın birimini yönetme yetkisi varsa kullanıcıları görebilir
        if ($this->hasCascadePermission($user, PermissionType::MANAGE_UNIT->value, null, ['unit_id' => $targetUser->unit_id])) {
            return true;
        }
        foreach ($targetUser->getAffiliations() as $aff) {
            if ($this->hasCascadePermission($user, PermissionType::MANAGE_UNIT->value, null, ['unit_id' => $aff->unit_id])) return true;
        }

        // Kullanıcıları yönetme özel yetkisi (Birim, Bölüm veya Program bazında)
        return $this->checkPermissionWithAffiliations($user, PermissionType::MANAGE_USERS->value, $targetUser);
    }

    /**
     * Yeni kullanıcı ekleme yetkisi
     */
    public function create(User $user, $model = null, $userData = null): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            if (isset($userData->unit_id)) {
                return !is_null($user->unit_id) && $user->unit_id == $userData->unit_id;
            }
            return !is_null($user->unit_id);
        }

        if ($user->role === 'department_head' && isset($userData->department_id)) {
             return $user->department_id == $userData->department_id;
        }

        if (isset($userData->department_id) || isset($userData->program_id) || isset($userData->unit_id)) {
            // Kullanıcıları yönetme özel yetkisi (Birim, Bölüm veya Program bazında)
            return $this->hasCascadePermission($user, PermissionType::MANAGE_USERS->value, null, [
                'unit_id' => $userData->unit_id ?? null,
                'department_id' => $userData->department_id ?? null,
                'program_id' => $userData->program_id ?? null
            ]);
        }

        return $this->hasCascadePermission($user, PermissionType::MANAGE_USERS->value);
    }

    /**
     * Kullanıcı güncelleme yetkisi
     */
    public function update(User $user, User $targetUser): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            if (!is_null($user->unit_id)) {
                if ($user->unit_id == $targetUser->unit_id) return true;
            }
        }

        // Kullanıcı kendi profilini güncelleyebilir
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Bölüm başkanı sadece kendi bölümündeki kullanıcıları güncelleyebilir
        if ($user->role === 'department_head') {
            if ($user->department_id === $targetUser->department_id) return true;
            return false;
        }

        // Kullanıcının targetUser'ın birimini yönetme yetkisi varsa kullanıcıları görebilir
        if ($this->hasCascadePermission($user, PermissionType::MANAGE_UNIT->value, null, ['unit_id' => $targetUser->unit_id])) {
            return true;
        }

        // Kullanıcıları yönetme özel yetkisi (Birim, Bölüm veya Program bazında)
        return $this->hasCascadePermission($user, PermissionType::MANAGE_USERS->value, null, [
            'unit_id' => $targetUser->unit_id,
            'department_id' => $targetUser->department_id,
            'program_id' => $targetUser->program_id
        ]);
    }

    /**
     * Kullanıcı silme yetkisi
     */
    public function delete(User $user, User $targetUser): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            if (!is_null($user->unit_id)) {
                if ($user->unit_id == $targetUser->unit_id) return true;
            }
        }

        if ($user->role === 'department_head') {
             if ($user->department_id === $targetUser->department_id) return true;
             return false;
        }

        // Kullanıcının targetUser'ın birimini yönetme yetkisi varsa kullanıcıları görebilir
        if ($this->hasCascadePermission($user, PermissionType::MANAGE_UNIT->value, null, ['unit_id' => $targetUser->unit_id])) {
            return true;
        }

        // Kullanıcıları yönetme özel yetkisi (Birim, Bölüm veya Program bazında)
        return $this->hasCascadePermission($user, PermissionType::MANAGE_USERS->value, null, [
            'unit_id' => $targetUser->unit_id,
            'department_id' => $targetUser->department_id,
            'program_id' => $targetUser->program_id
        ]);
    }

    /**
     * Kullanıcıları yönetme yetkisi
     */
    public function manage_users(User $user, User $targetUser): bool
    {
        return $this->update($user, $targetUser);
    }

    /**
     * Kullanıcının ders/sınav programını yönetme yetkisi
     */
    public function manage_schedule(User $user, User $targetUser): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            if (!is_null($user->unit_id)) {
                if ($user->unit_id == $targetUser->unit_id || empty($targetUser->unit_id)) return true;
                foreach ($targetUser->getAffiliations() as $aff) {
                    if ($aff->unit_id == $user->unit_id) return true;
                }
            }
        }

        if ($user->role === 'department_head') {
            if (!empty($targetUser->department_id) && $user->department_id === $targetUser->department_id) return true;
            foreach ($targetUser->getAffiliations() as $aff) {
                if ($aff->department_id === $user->department_id) return true;
            }
            return false;
        }

        if ($this->hasCascadePermission($user, PermissionType::MANAGE_UNIT->value, null, ['unit_id' => $targetUser->unit_id])) {
            return true;
        }
        foreach ($targetUser->getAffiliations() as $aff) {
            if ($this->hasCascadePermission($user, PermissionType::MANAGE_UNIT->value, null, ['unit_id' => $aff->unit_id])) return true;
        }

        return $this->checkPermissionWithAffiliations($user, PermissionType::MANAGE_SCHEDULE->value, $targetUser);
    }
}

