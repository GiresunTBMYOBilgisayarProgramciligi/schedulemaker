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
        return $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'department_head' || Gate::hasAnyPermission($user->id, PermissionType::MANAGE_LESSONS->value);
    }

    /**
     * Ders detayını görme yetkisi
     */
    public function view(User $user, Lesson $lesson): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            $lessonUnitId = $lesson->department ? $lesson->department->unit_id : (new \App\Models\Department())->find($lesson->department_id)?->unit_id;
            if (!is_null($user->unit_id) && $user->unit_id == $lessonUnitId) {
                return true;
            }
        }

        // Dersi veren akademisyen
        if ($user->id === $lesson->lecturer_id) {
            return true;
        }

        // Dersin bağlı olduğu bölümün başkanı
        if ($user->role === 'department_head') {
            return $user->department_id === $lesson->department_id;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_LESSONS->value, $lesson) ||
               Gate::hasCascadePermission($user->id, PermissionType::MANAGE_SCHEDULE->value, $lesson);
    }

    /**
     * Yeni ders ekleme yetkisi
     */
    public function create(User $user, $lessonData = null): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            if (isset($lessonData->department_id)) {
                $dept = (new \App\Models\Department())->find($lessonData->department_id);
                if ($dept && !is_null($user->unit_id) && $user->unit_id == $dept->unit_id) {
                    return true;
                }
            }
            return !is_null($user->unit_id);
        }

        if ($user->role === 'department_head') {
             if (isset($lessonData->department_id)) {
                 return $user->department_id == $lessonData->department_id;
             }
             return true;
        }

        if (isset($lessonData->department_id)) {
            return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_LESSONS->value, null, ['department_id' => $lessonData->department_id]) ||
                   Gate::hasCascadePermission($user->id, PermissionType::MANAGE_SCHEDULE->value, null, ['department_id' => $lessonData->department_id]);
        }

        return false;
    }

    /**
     * Ders güncelleme yetkisi
     */
    public function update(User $user, Lesson $lesson): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            $lessonUnitId = $lesson->department ? $lesson->department->unit_id : (new \App\Models\Department())->find($lesson->department_id)?->unit_id;
            if (!is_null($user->unit_id) && $user->unit_id == $lessonUnitId) {
                return true;
            }
        }

        // Akademisyen kendi dersini güncelleyebilir (bazı alanlar kısıtlı olabilir ama yetki var)
        if ($user->id === $lesson->lecturer_id) {
            return true;
        }

        // Bölüm başkanı kendi bölümünün derslerini güncelleyebilir
        if ($user->role === 'department_head') {
            return $user->department_id === $lesson->department_id;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_LESSONS->value, $lesson) ||
               Gate::hasCascadePermission($user->id, PermissionType::MANAGE_SCHEDULE->value, $lesson);
    }

    /**
     * Ders silme yetkisi
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            $lessonUnitId = $lesson->department ? $lesson->department->unit_id : (new \App\Models\Department())->find($lesson->department_id)?->unit_id;
            if (!is_null($user->unit_id) && $user->unit_id == $lessonUnitId) {
                return true;
            }
        }

        // Bölüm başkanı kendi bölümünün derslerini silebilir
        if ($user->role === 'department_head') {
            return $user->department_id === $lesson->department_id;
        }

        return Gate::hasCascadePermission($user->id, PermissionType::MANAGE_LESSONS->value, $lesson) ||
               Gate::hasCascadePermission($user->id, PermissionType::MANAGE_SCHEDULE->value, $lesson);
    }

    /**
     * Ders birleştirme yetkisi
     */
    public function combine(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager';
    }
}
