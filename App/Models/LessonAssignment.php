<?php

namespace App\Models;

use App\Core\Model;
use Exception;

class LessonAssignment extends Model
{
    protected string $table_name = 'lesson_assignments';

    public ?int $id = null;
    public int $lesson_id;
    public int $lecturer_id;
    public string $semester;
    public string $academic_year;

    public ?Lesson $lesson = null;
    public ?User $lecturer = null;

    protected array $excludeFromDb = ['lesson', 'lecturer'];

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getLessonRelation(array $results, array $options = []): array
    {
        $lessonIds = array_column($results, 'lesson_id');
        $lessonIds = array_unique(array_filter($lessonIds));
        if (empty($lessonIds)) {
            return $results;
        }

        $query = (new Lesson())->get()->where(['id' => ['in' => $lessonIds]]);
        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $lessons = $query->all();
        $lessonsKeyed = [];
        foreach ($lessons as $l) {
            $lessonsKeyed[$l->id] = $l;
        }

        foreach ($results as &$row) {
            $row['lesson'] = isset($row['lesson_id']) && isset($lessonsKeyed[$row['lesson_id']])
                ? $lessonsKeyed[$row['lesson_id']]
                : null;
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getLecturerRelation(array $results, array $options = []): array
    {
        $lecturerIds = array_column($results, 'lecturer_id');
        $lecturerIds = array_unique(array_filter($lecturerIds));
        if (empty($lecturerIds)) {
            return $results;
        }

        $query = (new User())->get()->where(['id' => ['in' => $lecturerIds]]);
        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
        $usersKeyed = [];
        foreach ($users as $u) {
            $usersKeyed[$u->id] = $u;
        }

        foreach ($results as &$row) {
            $row['lecturer'] = isset($row['lecturer_id']) && isset($usersKeyed[$row['lecturer_id']])
                ? $usersKeyed[$row['lecturer_id']]
                : null;
        }
        return $results;
    }
}
