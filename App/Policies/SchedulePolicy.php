<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Schedule;
use App\Models\Program;
use App\Models\Lesson;
use App\Models\Department;

class SchedulePolicy extends BasePolicy
{
    /**
     * Program düzenleme/güncelleme yetkisi
     */
    public function update(User $user, Schedule $schedule): bool
    {
        if ($user->role === 'manager' || $user->role === 'submanager') {
            return true;
        }

        switch ($schedule->owner_type) {
            case 'program':
                $program = (new Program())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($program) {
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
                    return $scheduleUser->department->chairperson_id == $user->id || $scheduleUser->id == $user->id;
                }
                break;

            case 'lesson':
                $lesson = (new Lesson())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($lesson) {
                    return $lesson->department->chairperson_id == $user->id;
                }
                break;

            case 'classroom':
                // Sınıf programları için genellikle üst yönetim yetkilidir, 
                // ancak canUserDoAction her zaman true döner. 
                // Bu politikayı canUserDoAction ile uyumlu tutuyoruz.
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
}
