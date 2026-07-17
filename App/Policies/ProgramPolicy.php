<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Program;
use App\Models\Department;
use App\Core\Gate;
use App\Enums\PermissionType;

class ProgramPolicy extends BasePolicy
{
    /**
     * Program listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'department_head';
    }

    /**
     * Program detayını görme yetkisi
     */
    public function view(User $user, Program $program): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Programın bağlı olduğu bölümün başkanı
        if ($user->role === 'department_head') {
            return $user->department_id === $program->department_id;
        }

        // Sisteme kayıtlı olduğu program ise (Akademisyen veya Öğrenci)
        if ($user->program_id === $program->id) {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_PROGRAM->value, $program);
    }

    /**
     * Yeni program ekleme yetkisi
     */
    public function create(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }

    /**
     * Program güncelleme yetkisi
     */
    public function update(User $user, Program $program): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Bölüm başkanı kendi programlarını güncelleyebilir
        if ($user->role === 'department_head') {
            return $user->department_id === $program->department_id;
        }

        // Sisteme kayıtlı olduğu program ise (Akademisyen veya Öğrenci) - Sahiplik Kontrolü
        if ($user->program_id === $program->id) {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_PROGRAM->value, $program);
    }

    /**
     * Program silme yetkisi
     */
    public function delete(User $user, Program $program): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_PROGRAM->value, $program);
    }
}
