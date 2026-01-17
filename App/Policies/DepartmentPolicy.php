<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Department;

class DepartmentPolicy extends BasePolicy
{
    /**
     * Bölüm listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'department_head';
    }

    /**
     * Bölüm detayını görme yetkisi
     */
    public function view(User $user, Department $department): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Bölüm Başkanı ise
        if ($user->id === $department->chairperson_id) {
            return true;
        }

        // Bölüme kayıtlı kullanıcı ise
        if ($user->department_id === $department->id) {
            return true;
        }

        return false;
    }

    /**
     * Yeni bölüm ekleme yetkisi
     */
    public function create(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Bölüm güncelleme yetkisi
     */
    public function update(User $user, Department $department): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Sadece kendi bölümünün başkanı güncelleyebilir mi? 
        // canUserDoAction mantığını koruyoruz:
        return $user->id === $department->chairperson_id || $user->department_id === $department->id;
    }

    /**
     * Bölüm silme yetkisi
     */
    public function delete(User $user, Department $department): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }
}
