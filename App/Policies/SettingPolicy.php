<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Setting;

class SettingPolicy extends BasePolicy
{
    /**
     * Ayarları görüntüleme yetkisi
     */
    /**
     * Listeleme yetkisi
     */
    public function list(User $user): bool
    {
        return false;
    }

    public function view(User $user): bool
    {
        return false;
    }

    /**
     * Yeni ayar ekleme yetkisi
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Ayar güncelleme yetkisi
     */
    public function update(User $user, Setting $setting): bool
    {
        return false;
    }

    /**
     * Ayar silme yetkisi
     */
    public function delete(User $user, Setting $setting): bool
    {
        return false;
    }
}
