<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ScheduleNote;
use App\DTOs\ScheduleNoteDTO;
use App\Enums\UserRole;
use App\Enums\PermissionType;
use App\Repositories\DepartmentRepository;

class ScheduleNotePolicy extends BasePolicy
{
    /**
     * Akademisyenin not ekleme / güncelleme yetkisi (Kendi notu için veya Düzenleyici yetkisi)
     */
    public function create(User $user, mixed $model = null, ?ScheduleNoteDTO $dto = null): bool
    {
        if ($dto !== null) {
            if ((int)$user->id === (int)$dto->userId) {
                return true;
            }
            return $this->canManageNotes($user);
        }
        return true;
    }

    /**
     * Not okuma yetkisi (Kendi notu veya Düzenleyici yetkisi)
     */
    public function view(User $user, mixed $model = null, mixed $dto = null): bool
    {
        $targetUserId = null;
        if ($model instanceof ScheduleNote) {
            $targetUserId = $model->user_id;
        } elseif (is_numeric($dto)) {
            $targetUserId = (int)$dto;
        } elseif (is_numeric($model)) {
            $targetUserId = (int)$model;
        }

        if ($targetUserId !== null && (int)$user->id === (int)$targetUserId) {
            return true;
        }

        return $this->canManageNotes($user);
    }

    /**
     * Not silme yetkisi (Not sahibi veya Düzenleyici yetkisi)
     */
    public function delete(User $user, ScheduleNote $note): bool
    {
        if ((int)$user->id === (int)$note->user_id) {
            return true;
        }

        return $this->canManageNotes($user);
    }

    /**
     * Program düzenleyicisi olarak notları görme, görüldü yapma ve durum güncelleme yetkisi
     */
    public function canManageNotes(User $user): bool
    {
        if (in_array($user->role, [
            UserRole::Admin->value,
            UserRole::Manager->value,
            UserRole::SubManager->value,
            UserRole::DepartmentHead->value,
        ], true)) {
            return true;
        }

        // Bölüm başkanı mı? (chairperson_id == $user->id)
        if ((new DepartmentRepository())->isChairpersonOfAnyDepartment($user->id)) {
            return true;
        }

        // Ders programı yönetme özel yetkisi var mı?
        if ($this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value)) {
            return true;
        }

        return false;
    }
}
