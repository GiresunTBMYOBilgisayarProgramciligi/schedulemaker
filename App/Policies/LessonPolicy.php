<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Lesson;

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

        return false;
    }

    /**
     * Yeni ders ekleme yetkisi
     */
    public function create(User $user): bool
    {
        // En az Bölüm Başkanı olmalı
        return $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'department_head';
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
