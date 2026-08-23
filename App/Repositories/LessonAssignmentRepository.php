<?php

namespace App\Repositories;

use App\Models\LessonAssignment;
use App\Core\EventDispatcher;
use App\Events\LessonAssignedEvent;
use function App\Helpers\getSettingValue;
use Exception;

class LessonAssignmentRepository extends BaseRepository
{
    protected string $modelClass = LessonAssignment::class;

    /**
     * Belirli bir ders, hoca, dönem ve akademik yıl için atamayı kaydeder veya günceller (upsert).
     *
     * @param int $lessonId
     * @param int $lecturerId
     * @param string $semester
     * @param string $academicYear
     * @return LessonAssignment
     * @throws Exception
     */
    public function upsert(int $lessonId, int $lecturerId, string $semester, string $academicYear): LessonAssignment
    {
        /** @var LessonAssignment $model */
        $model = new $this->modelClass;
        $existing = $model->get()->where([
            'lesson_id' => $lessonId,
            'semester' => $semester,
            'academic_year' => $academicYear
        ])->first();

        if ($existing) {
            $existing->lecturer_id = $lecturerId;
            $existing->update();
            EventDispatcher::getInstance()->dispatch(new LessonAssignedEvent($existing));
            return $existing;
        }

        $newAssignment = new LessonAssignment();
        $newAssignment->lesson_id = $lessonId;
        $newAssignment->lecturer_id = $lecturerId;
        $newAssignment->semester = $semester;
        $newAssignment->academic_year = $academicYear;
        $newAssignment->create();
        
        EventDispatcher::getInstance()->dispatch(new LessonAssignedEvent($newAssignment));
        return $newAssignment;
    }

    /**
     * Belirli dönem ve yıldaki ders atamasını getirir.
     */
    public function findByLessonAndPeriod(int $lessonId, string $semester, string $academicYear): ?LessonAssignment
    {
        /** @var LessonAssignment $model */
        $model = new $this->modelClass;
        return $model->get()->where([
            'lesson_id' => $lessonId,
            'semester' => $semester,
            'academic_year' => $academicYear
        ])->with(['lecturer', 'lesson'])->first();
    }

    /**
     * Aktif dönem ders atamasını getirir.
     */
    public function findActiveAssignmentForLesson(int $lessonId): ?LessonAssignment
    {
        $semester = getSettingValue('semester');
        $academicYear = getSettingValue('academic_year');
        return $this->findByLessonAndPeriod($lessonId, $semester, $academicYear);
    }

    /**
     * Aktif dönemdeki hocaya ait atamaları getirir.
     */
    public function findActiveAssignmentsForLecturer(int $lecturerId): array
    {
        $semester = getSettingValue('semester');
        $academicYear = getSettingValue('academic_year');
        return $this->findByLecturer($lecturerId, $semester, $academicYear);
    }

    /**
     * Bir dersin tüm veya filtrelenmiş atamalarını getirir.
     */
    public function findByLesson(int $lessonId, ?string $semester = null, ?string $academicYear = null): array
    {
        /** @var LessonAssignment $model */
        $model = new $this->modelClass;
        $filters = ['lesson_id' => $lessonId];
        if (!is_null($semester)) {
            $filters['semester'] = $semester;
        }
        if (!is_null($academicYear)) {
            $filters['academic_year'] = $academicYear;
        }
        return $model->get()->where($filters)->with(['lecturer', 'lesson'])->all();
    }

    /**
     * Bir hocanın tüm veya filtrelenmiş atamalarını getirir.
     */
    public function findByLecturer(int $lecturerId, ?string $semester = null, ?string $academicYear = null): array
    {
        /** @var LessonAssignment $model */
        $model = new $this->modelClass;
        $filters = ['lecturer_id' => $lecturerId];
        if (!is_null($semester)) {
            $filters['semester'] = $semester;
        }
        if (!is_null($academicYear)) {
            $filters['academic_year'] = $academicYear;
        }
        return $model->get()->where($filters)->with(['lesson' => ['with' => ['program', 'department', 'building', 'parentLesson']]])->all();
    }

}
