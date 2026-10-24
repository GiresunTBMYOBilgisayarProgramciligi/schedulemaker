<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ScheduleNote;
use App\DTOs\ScheduleNoteDTO;
use App\Enums\UserRole;
use App\Enums\PermissionType;
use App\Models\Schedule;
use App\Core\Gate;
use App\Enums\OwnerType;
use App\Repositories\DepartmentRepository;
use App\Repositories\ScheduleNoteRepository;

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
            return $this->canManageLecturerNotes($user, (int)$dto->userId);
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

        if ($targetUserId === null) {
            return $this->canManageNotes($user);
        }

        return $this->canManageLecturerNotes($user, $targetUserId);
    }

    /**
     * Not silme yetkisi (Not sahibi veya hocanın programlarını düzenleme yetkisi olan düzenleyici)
     */
    public function delete(User $user, ScheduleNote $note): bool
    {
        if ((int)$user->id === (int)$note->user_id) {
            return true;
        }

        return $this->canManageLecturerNotes($user, (int)$note->user_id);
    }

    /**
     * Notu düzenleyici olarak görme / durum güncelleme yetkisi (hocanın programlarını düzenleme kapsamına bağlı)
     */
    public function manageNote(User $user, ScheduleNote $note): bool
    {
        return $this->canManageLecturerNotes($user, (int)$note->user_id);
    }

    /**
     * Kullanıcı, hocanın notlarını düzenleyici olarak yönetebilir mi?
     *
     * Kural: Hocanın kendi programını (kullanıcı programı) veya bulunduğu programlardan
     * (kadro, affiliation, ders ataması) herhangi birini düzenleme yetkisi olan herkes notları yönetebilir.
     */
    public function canManageLecturerNotes(User $user, int $lecturerId): bool
    {
        static $cache = [];
        $key = $user->id . ':' . $lecturerId;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        if ((int)$user->id === $lecturerId || $user->role === UserRole::Admin->value) {
            return $cache[$key] = true;
        }
        if (!$this->canManageNotes($user)) {
            return $cache[$key] = false;
        }

        $userSchedule = new Schedule();
        $userSchedule->owner_type = OwnerType::USER->value;
        $userSchedule->owner_id = $lecturerId;
        if (Gate::check(PermissionType::UPDATE->value, $userSchedule)) {
            return $cache[$key] = true;
        }

        foreach ((new ScheduleNoteRepository())->getLecturerProgramIds($lecturerId) as $programId) {
            $programSchedule = new Schedule();
            $programSchedule->owner_type = OwnerType::PROGRAM->value;
            $programSchedule->owner_id = $programId;
            if (Gate::check(PermissionType::UPDATE->value, $programSchedule)) {
                return $cache[$key] = true;
            }
        }

        return $cache[$key] = false;
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
