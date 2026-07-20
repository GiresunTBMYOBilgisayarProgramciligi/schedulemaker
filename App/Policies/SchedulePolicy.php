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
        if ($user->role === 'manager' || ($user->role === 'submanager' && $user->unit_id == (new \App\Models\Department())->find((new \App\Models\Lesson())->find($schedule->lesson_id)->department_id)->unit_id)) {
            return true;
        }

        switch ($schedule->owner_type) {
            case 'program':
                $program = (new Program())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($program) {
                    if (Gate::hasCascadePermission($user->id, PermissionType::MANAGE_SCHEDULE->value, $program)) return true;
                    
                    return $program->department->chairperson_id == $user->id;
                }
                break;

            case 'user':
                $scheduleUser = (new User())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($scheduleUser) {
                    // Hoca bölümsüzse veya kendi programıysa veya bölüm başkanıysa
                    if (!$scheduleUser->department_id) {
                        return true;
                    }
                    if (Gate::hasCascadePermission($user->id, PermissionType::MANAGE_SCHEDULE->value, null, ['department_id' => $scheduleUser->department_id])) return true;

                    return $scheduleUser->department->chairperson_id == $user->id || $scheduleUser->id == $user->id;
                }
                break;

            case 'lesson':
                $lesson = (new Lesson())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($lesson) {
                    if (Gate::hasCascadePermission($user->id, PermissionType::MANAGE_SCHEDULE->value, null, ['department_id' => $lesson->department_id])) return true;

                    return $lesson->department->chairperson_id == $user->id;
                }
                break;

            case 'classroom':
                // Sınıf programları için genellikle üst yönetim yetkilidir, 
                return true;
        }

        return false;
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
