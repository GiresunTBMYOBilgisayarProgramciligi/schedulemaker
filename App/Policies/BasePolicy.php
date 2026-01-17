<?php

namespace App\Policies;

use App\Models\User;

/**
 * Tüm yetki sınıfları için temel sınıf
 */
abstract class BasePolicy
{
    /**
     * Herhangi bir yetki kontrolünden önce çalıştırılır.
     * Eğer true dönerse yetki her durumda verilir (örn. Admin için).
     *
     * @param User $user
     * @param string $action
     * @return bool|null
     */
    public function before(User $user, string $action): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        return null;
    }
}
