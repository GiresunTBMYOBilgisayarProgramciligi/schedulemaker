<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Setting;

class SettingPolicy extends BasePolicy
{
    /**
     * Ayarları görüntüleme yetkisi
     */
    public function view(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Yeni ayar ekleme yetkisi
     */
    public function create(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Ayar güncelleme yetkisi
     */
    public function update(User $user, Setting $setting): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Ayar silme yetkisi
     */
    public function delete(User $user, Setting $setting): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }
}
