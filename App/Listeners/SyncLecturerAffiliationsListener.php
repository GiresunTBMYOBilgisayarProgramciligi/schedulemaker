<?php

namespace App\Listeners;

use App\Events\LessonAssignedEvent;
use App\Models\Lesson;
use App\Models\UserAffiliation;
use App\Models\Program;
use App\Models\Department;

class SyncLecturerAffiliationsListener
{
    public function handle(LessonAssignedEvent $event): void
    {
        $assignment = $event->assignment;
        $lecturerId = $assignment->lecturer_id;
        $lessonId = $assignment->lesson_id;

        $lesson = (new Lesson())->find($lessonId);
        if (!$lesson) {
            return;
        }

        $programId = $lesson->program_id;
        $departmentId = $lesson->department_id;
        $unitId = null;

        if ($programId && !$departmentId) {
            $departmentId = (new Program())->find($programId)?->department_id;
        }
        if ($departmentId) {
            $unitId = (new Department())->find($departmentId)?->unit_id;
        }

        if (!$unitId && !$departmentId && !$programId) {
            return;
        }

        $lecturer = clone (new \App\Models\User())->find($lecturerId);
        if ($lecturer && $lecturer->unit_id == $unitId && $lecturer->department_id == $departmentId && $lecturer->program_id == $programId) {
            return;
        }

        $where = [
            'user_id' => $lecturerId
        ];

        // SQL'de NULL araması IS NULL ile yapıldığı için Model sınıfının null filter handlingine dikkat edilmeli. 
        // Eğer Model sınıfı array syntaxı ('unit_id' => null) desteklemiyorsa bu sorgu patlayabilir.
        // Güvenli olması için model üzerinden manuel kontrol yapalım veya tam olarak eşleşen propertyler atayalım.
        
        $affiliations = (new UserAffiliation())->get()->where(['user_id' => $lecturerId])->all();
        $exists = false;
        foreach ($affiliations as $affil) {
            if ($affil->unit_id == $unitId && $affil->department_id == $departmentId && $affil->program_id == $programId) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $affiliation = new UserAffiliation();
            $affiliation->user_id = $lecturerId;
            $affiliation->unit_id = $unitId;
            $affiliation->department_id = $departmentId;
            $affiliation->program_id = $programId;
            $affiliation->create();
        }
    }
}
