<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Lesson;
use App\Core\Gate;
use App\Enums\PermissionType;

class LessonPolicy extends BasePolicy
{
    /**
     * Ders listesini görme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'department_head';
    }

    /**
     * Ders detayını görme yetkisi
     */
    public function view(User $user, Lesson $lesson): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Dersi veren akademisyen
        if ($user->id === $lesson->lecturer_id) {
            return true;
        }

        // Dersin bağlı olduğu bölümün başkanı
        if ($user->role === 'department_head') {
            return $user->department_id === $lesson->department_id;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::VIEW->value, $perms['departments'][$lesson->department_id] ?? []) || in_array(PermissionType::MANAGE_SCHEDULE->value, $perms['departments'][$lesson->department_id] ?? []) || in_array(PermissionType::MANAGE_LESSONS->value, $perms['departments'][$lesson->department_id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Yeni ders ekleme yetkisi
     */
    public function create(User $user, ?array $lessonData = null): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        if ($user->role === 'department_head' && isset($lessonData['department_id'])) {
             return $user->department_id == $lessonData['department_id'];
        }

        if (isset($lessonData['department_id'])) {
            $perms = Gate::getUserPermissions($user->id);
            if (in_array(PermissionType::MANAGE_SCHEDULE->value, $perms['departments'][$lessonData['department_id']] ?? []) || in_array(PermissionType::MANAGE_LESSONS->value, $perms['departments'][$lessonData['department_id']] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ders güncelleme yetkisi
     */
    public function update(User $user, Lesson $lesson): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Akademisyen kendi dersini güncelleyebilir (bazı alanlar kısıtlı olabilir ama yetki var)
        if ($user->id === $lesson->lecturer_id) {
            return true;
        }

        // Bölüm başkanı kendi bölümünün derslerini güncelleyebilir
        if ($user->role === 'department_head') {
            return $user->department_id === $lesson->department_id;
        }

        $perms = Gate::getUserPermissions($user->id);
        if (in_array(PermissionType::MANAGE_SCHEDULE->value, $perms['departments'][$lesson->department_id] ?? []) || in_array(PermissionType::MANAGE_LESSONS->value, $perms['departments'][$lesson->department_id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Ders silme yetkisi
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        // Bölüm başkanı kendi bölümünün derslerini silebilir
        if ($user->role === 'department_head') {
            return $user->department_id === $lesson->department_id;
        }

        $perms = Gate::getUserPermissions($user->id);
        if ((in_array(PermissionType::MANAGE_SCHEDULE->value, $perms['departments'][$lesson->department_id] ?? []) || in_array(PermissionType::MANAGE_LESSONS->value, $perms['departments'][$lesson->department_id] ?? [])) && in_array(PermissionType::DELETE->value, $perms['departments'][$lesson->department_id] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Ders birleştirme yetkisi
     */
    public function combine(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }
}
