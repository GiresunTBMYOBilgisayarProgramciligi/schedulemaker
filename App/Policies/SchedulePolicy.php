<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Schedule;
use App\Models\Program;
use App\Models\Lesson;
use App\Models\Department;
use App\Core\Gate;
use App\Enums\PermissionType;

class SchedulePolicy extends BasePolicy
{
    /**
     * Program düzenleme/güncelleme yetkisi
     */
    /**
     * Listeleme yetkisi
     */
    public function list(User $user): bool
    {
        return $user->role === 'manager' || $user->role === 'submanager' || $user->role === 'admin';
    }

    public function update(User $user, Schedule $schedule): bool
    {
        switch ($schedule->owner_type) {
            case 'program':
                $program = (new Program())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($program) {
                    if ($user->role === 'manager' || $user->role === 'submanager') {
                        if (is_null($user->unit_id) || $program->department->unit_id == $user->unit_id) return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, $program)) return true;
                    
                    return $program->department->chairperson_id == $user->id;
                }
                break;

            case 'user':
                $scheduleUser = (new User())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($scheduleUser) {
                    if ($user->role === 'manager' || $user->role === 'submanager') {
                        if (is_null($user->unit_id)) return true;
                        if ($scheduleUser->department_id && $scheduleUser->department->unit_id == $user->unit_id) return true;
                        if (empty($scheduleUser->department_id) && $scheduleUser->unit_id == $user->unit_id) return true;
                    }
                    // Hoca bölümsüzse veya kendi programıysa veya bölüm başkanıysa
                    if (!$scheduleUser->department_id) {
                        return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, null, ['department_id' => $scheduleUser->department_id])) return true;

                    return $scheduleUser->department->chairperson_id == $user->id || $scheduleUser->id == $user->id;
                }
                break;

            case 'lesson':
                $lesson = (new Lesson())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($lesson) {
                    if ($user->role === 'manager' || $user->role === 'submanager') {
                        if (is_null($user->unit_id) || $lesson->department->unit_id == $user->unit_id) return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, null, ['department_id' => $lesson->department_id])) return true;

                    return $lesson->department->chairperson_id == $user->id;
                }
                break;

            case 'classroom':
                // Sınıf programları için genellikle üst yönetim yetkilidir
                return true;
        }

        return false;
    }

    /**
     * Program takvimini yönetme yetkisi
     */
    public function manage_schedule(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    /**
     * Program silme yetkisi (Genellikle update ile aynı)
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    /**
     * Program görüntüleme yetkisi
     */
    public function view(?User $user, Schedule $schedule): bool
    {
        return true;//home sayfasında tüm programlar gösterildiği için herkes görebilir. İlerde yetki kontrolü gerekebilir.
    }
}
