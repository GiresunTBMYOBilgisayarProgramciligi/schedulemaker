<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Schedule;
use App\Models\Program;
use App\Models\Lesson;
use App\Models\Department;
use App\Models\Classroom;
use App\Enums\PermissionType;
use App\Enums\UserRole;

class SchedulePolicy extends BasePolicy
{
    /**
     * Listeleme yetkisi
     */
    public function list(User $user): bool
    {
        return $this->hasRole($user, UserRole::SubManager);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        switch ($schedule->owner_type) {
            case 'program':
                $program = (new Program())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($program) {
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id) || ($program->department && $program->department->unit_id == $user->unit_id)) return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, $program)) return true;
                    
                    return !empty($program->department?->chairperson_id) && $program->department->chairperson_id == $user->id;
                }
                break;

            case 'user':
                $scheduleUser = (new User())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($scheduleUser) {
                    $affiliations = $scheduleUser->getAffiliations();
                    
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id)) return true;
                        if ($scheduleUser->department_id && $scheduleUser->department && $scheduleUser->department->unit_id == $user->unit_id) return true;
                        if (empty($scheduleUser->department_id) && $scheduleUser->unit_id == $user->unit_id) return true;
                        
                        foreach ($affiliations as $aff) {
                            if ($aff->unit_id == $user->unit_id) return true;
                        }
                    }
                    
                    // Kendi programıysa
                    if ($scheduleUser->id == $user->id) {
                        return true;
                    }
                    
                    if ($this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, null, ['department_id' => $scheduleUser->department_id])) return true;
                    if (!empty($scheduleUser->department?->chairperson_id) && $scheduleUser->department->chairperson_id == $user->id) return true;
                    
                    // Affiliations check for department head / cascade
                    foreach ($affiliations as $aff) {
                        if ($aff->department_id) {
                            if ($this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, null, ['department_id' => $aff->department_id])) return true;
                            
                            $affDept = (new Department())->find($aff->department_id);
                            if ($affDept && $affDept->chairperson_id == $user->id) return true;
                        }
                    }
                }
                break;

            case 'lesson':
                $lesson = (new Lesson())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($lesson) {
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id) || ($lesson->department && $lesson->department->unit_id == $user->unit_id)) return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, null, ['department_id' => $lesson->department_id])) return true;
                    if (!empty($lesson->department?->chairperson_id) && $lesson->department->chairperson_id == $user->id) return true;
                }
                break;

            case 'classroom':
                $classroom = (new Classroom())->where(["id" => $schedule->owner_id])->with(['building'])->first();
                if ($classroom) {
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id) || ($classroom->building && $classroom->building->unit_id == $user->unit_id)) return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::MANAGE_SCHEDULE->value, null)) return true;
                }
                break;
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
     * Program silme yetkisi
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
        if (!$schedule->is_published) {
            if (!$user) return false;
            if ($this->hasRole($user, UserRole::DepartmentHead)) return true;
            if ($this->update($user, $schedule)) return true;

            // Sınıf programları sisteme giriş yapmış yetkili kullanıcılar tarafından görüntülenebilir
            if ($schedule->owner_type === 'classroom') {
                return true;
            }

            // Hoca programı: Ders programı yönetme yetkisi (manage_schedule) olan kullanıcılar
            // çakışma ve müsaitlik kontrolü yapabilmek için hoca programını görüntüleyebilir
            if ($schedule->owner_type === 'user' && $this->hasAnyPermission($user, PermissionType::MANAGE_SCHEDULE->value)) {
                return true;
            }

            return false;
        }
        return true;
    }

    /**
     * Program öğesini kilitleme yetkisi
     */
    public function manage_lockScdheduleItem(User $user, Schedule $schedule): bool
    {
        switch ($schedule->owner_type) {
            case 'program':
                $program = (new Program())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($program) {
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id) || ($program->department && $program->department->unit_id == $user->unit_id)) return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::MANAGE_LOCK_SCHEDULE_ITEM->value, $program)) return true;
                }
                break;

            case 'user':
                $scheduleUser = (new User())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($scheduleUser) {
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id)) return true;
                        if ($scheduleUser->department_id && $scheduleUser->department && $scheduleUser->department->unit_id == $user->unit_id) return true;
                        if (empty($scheduleUser->department_id) && $scheduleUser->unit_id == $user->unit_id) return true;
                    }
                    if ($scheduleUser->department_id) {
                        if ($this->hasCascadePermission($user, PermissionType::MANAGE_LOCK_SCHEDULE_ITEM->value, null, ['department_id' => $scheduleUser->department_id])) return true;
                    }
                }
                break;

            case 'lesson':
                $lesson = (new Lesson())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($lesson) {
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id) || ($lesson->department && $lesson->department->unit_id == $user->unit_id)) return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::MANAGE_LOCK_SCHEDULE_ITEM->value, null, ['department_id' => $lesson->department_id])) return true;
                }
                break;

            case 'classroom':
                if ($this->hasRole($user, UserRole::SubManager)) return true;
                if ($this->hasCascadePermission($user, PermissionType::MANAGE_LOCK_SCHEDULE_ITEM->value, null)) return true;
                break;
        }

        return false;
    }
    /**
     * Program yayınlama yetkisi
     */
    public function publish_schedule(User $user, Schedule $schedule): bool
    {
        switch ($schedule->owner_type) {
            case 'program':
                $program = (new Program())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($program) {
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id) || ($program->department && $program->department->unit_id == $user->unit_id)) return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::PUBLISH_SCHEDULE->value, $program)) return true;
                }
                break;

            case 'user':
                $scheduleUser = (new User())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($scheduleUser) {
                    $affiliations = $scheduleUser->getAffiliations();
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id)) return true;
                        if ($scheduleUser->department_id && $scheduleUser->department && $scheduleUser->department->unit_id == $user->unit_id) return true;
                        if (empty($scheduleUser->department_id) && $scheduleUser->unit_id == $user->unit_id) return true;
                        
                        foreach ($affiliations as $aff) {
                            if ($aff->unit_id == $user->unit_id) return true;
                        }
                    }
                    if ($scheduleUser->department_id) {
                        if ($this->hasCascadePermission($user, PermissionType::PUBLISH_SCHEDULE->value, null, ['department_id' => $scheduleUser->department_id])) return true;
                    }
                    foreach ($affiliations as $aff) {
                        if ($aff->department_id) {
                            if ($this->hasCascadePermission($user, PermissionType::PUBLISH_SCHEDULE->value, null, ['department_id' => $aff->department_id])) return true;
                        }
                    }
                }
                break;

            case 'lesson':
                $lesson = (new Lesson())->where(["id" => $schedule->owner_id])->with(['department'])->first();
                if ($lesson) {
                    if ($this->hasRole($user, UserRole::SubManager)) {
                        if (is_null($user->unit_id) || ($lesson->department && $lesson->department->unit_id == $user->unit_id)) return true;
                    }
                    if ($this->hasCascadePermission($user, PermissionType::PUBLISH_SCHEDULE->value, null, ['department_id' => $lesson->department_id])) return true;
                }
                break;

            case 'classroom':
                if ($this->hasRole($user, UserRole::SubManager)) {
                    if (is_null($user->unit_id) || $user->role === 'admin') return true;
                    $classroom = (new Classroom())->where(["id" => $schedule->owner_id])->with(['building'])->first();
                    if ($classroom && $classroom->building && $classroom->building->unit_id == $user->unit_id) return true;
                }
                if ($this->hasCascadePermission($user, PermissionType::PUBLISH_SCHEDULE->value, null)) return true;
                break;
        }

        return false;
    }
}
