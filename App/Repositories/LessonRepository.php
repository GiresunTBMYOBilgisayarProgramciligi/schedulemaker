<?php

namespace App\Repositories;

use App\Models\Lesson;
use App\Models\LessonAssignment;

class LessonRepository extends BaseRepository
{
    protected string $modelClass = Lesson::class;

    /**
     * Bölüm başkanının görebileceği, kendi bölümüne ait tüm dersleri ilişkileriyle birlikte getirir.
     *
     * @param int $deptId Bölüm ID'si
     * @return Lesson[]
     * @throws \Exception
     */
    public function getLessonsForDepartmentHead(int $deptId): array
    {
        /** @var Lesson $model */
        $model = new $this->modelClass;
        return $model->get()->where(['department_id' => $deptId])
            ->with(['program', 'lecturer', 'department', 'building', 'parentLesson' => ['with' => ['program']]])
            ->all();
    }

    /**
     * Admin için sistemdeki tüm dersleri ilişkileriyle birlikte getirir.
     *
     * @return Lesson[]
     * @throws \Exception
     */
    public function getAllLessonsWithDetails(): array
    {
        /** @var Lesson $model */
        $model = new $this->modelClass;
        return $model->get()
            ->with(['program', 'lecturer', 'department', 'building', 'parentLesson' => ['with' => ['program']]])
            ->all();
    }

    /**
     * Belirtilen dersin detaylarını (bağlı dersler, programlar vb.) getirir.
     *
     * @param int $id Ders ID'si
     * @return Lesson|null
     * @throws \Exception
     */
    public function findLessonWithDetails(int $id): ?Lesson
    {
        /** @var Lesson $model */
        $model = new $this->modelClass;
        return $model->where(['id' => $id])
            ->with([
                'program', 
                'lecturer' => ['with' => ['lessons']], 
                'department', 
                'building',
                'assignments' => ['with' => ['lecturer']],
                'parentLesson' => ['with' => ['program']], 
                'childLessons' => ['with' => ['program']], 
                'examParentLesson' => ['with' => ['program']], 
                'examChildLessons' => ['with' => ['program']]
            ])
            ->first();

    }

    /**
     * Ders birleştirme için (aynı hocanın, aynı dönemde verdiği diğer dersler) aday ders listesini getirir.
     *
     * @param int $lecturerId Dersin hocasının ID'si
     * @param int $excludeLessonId Aramadan dışlanacak mevcut dersin ID'si
     * @param string $semester Yarıyıl (Güz/Bahar vb.)
     * @param string $academicYear Akademik yıl (Örn: 2023-2024)
     * @return Lesson[]
     * @throws \Exception
     */
    public function getCombineLessonList(?int $lecturerId, int $excludeLessonId, string $semester, string $academicYear): array
    {
        if (is_null($lecturerId)) {
            return [];
        }

        $asgns = (new LessonAssignment())->get()->where([
            'lecturer_id' => $lecturerId,
            'semester' => $semester,
            'academic_year' => $academicYear,
            '!lesson_id' => $excludeLessonId
        ])->all();


        $lessonIds = array_unique(array_column($asgns, 'lesson_id'));
        if (empty($lessonIds)) {
            return [];
        }

        /** @var Lesson $model */
        $model = new $this->modelClass;
        return $model->get()->where([
            'id' => ['in' => $lessonIds]
        ])->with([
            'program', 
            'lecturer' => ['semester' => $semester, 'academic_year' => $academicYear, 'with' => ['lessons']], 
            'department', 
            'parentLesson' => ['with' => ['program']], 
            'childLessons' => ['with' => ['program']]
        ])->all();
    }

    /**
     * Sınav birleştirme için aynı dönem ve akademik yıldaki diğer tüm dersleri getirir.
     *
     * @param int $excludeLessonId Aramadan dışlanacak mevcut dersin ID'si
     * @param string $semester Yarıyıl
     * @param string $academicYear Akademik yıl
     * @return Lesson[]
     * @throws \Exception
     */
    public function getExamCombineLessonList(int $excludeLessonId, string $semester, string $academicYear): array
    {
        $asgns = (new LessonAssignment())->get()->where([
            'semester' => $semester,
            'academic_year' => $academicYear,
            '!lesson_id' => $excludeLessonId
        ])->all();

        $lessonIds = array_unique(array_column($asgns, 'lesson_id'));
        if (empty($lessonIds)) {
            return [];
        }

        /** @var Lesson $model */
        $model = new $this->modelClass;
        return $model->get()->where([
            'id' => ['in' => $lessonIds]
        ])->with([
            'program', 
            'examParentLesson' => ['semester' => $semester, 'academic_year' => $academicYear]
        ])->all();
    }


}

