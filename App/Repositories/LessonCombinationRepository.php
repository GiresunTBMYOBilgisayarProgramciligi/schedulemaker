<?php

namespace App\Repositories;

use App\Models\LessonCombination;
use Exception;

class LessonCombinationRepository extends BaseRepository
{
    protected string $modelClass = LessonCombination::class;

    /**
     * Lesson + exam tipinde iki kombinasyon kaydı oluşturur.
     * combineLesson işleminde child'ı parent'a bağlamak için kullanılır.
     *
     * @param int    $parentId
     * @param int    $childId
     * @param string $semester
     * @param string $academicYear
     * @throws Exception
     */
    public function createLessonAndExamLink(int $parentId, int $childId, string $semester, string $academicYear): void
    {
        foreach (['lesson', 'exam'] as $type) {
            $lc = new LessonCombination();
            $lc->parent_lesson_id = $parentId;
            $lc->child_lesson_id  = $childId;
            $lc->type             = $type;
            $lc->semester         = $semester;
            $lc->academic_year    = $academicYear;
            $lc->create();
        }
    }

    /**
     * Sadece exam tipinde kombinasyon kaydı oluşturur.
     * combineExamLesson işleminde kullanılır.
     *
     * @param int    $parentId
     * @param int    $childId
     * @param string $semester
     * @param string $academicYear
     * @throws Exception
     */
    public function createExamLink(int $parentId, int $childId, string $semester, string $academicYear): void
    {
        $lc = new LessonCombination();
        $lc->parent_lesson_id = $parentId;
        $lc->child_lesson_id  = $childId;
        $lc->type             = 'exam';
        $lc->semester         = $semester;
        $lc->academic_year    = $academicYear;
        $lc->create();
    }

    /**
     * Bir child'ın alt child'larını (grandchild) yeni bir parent'a yeniden bağlar.
     * combineLesson sırasında child'ın mevcut alt derslerini de parent'a taşır.
     *
     * @param int    $grandChildId  Yeniden bağlanacak alt child dersin ID'si
     * @param int    $newParentId   Hedef parent dersin ID'si
     * @param string $semester
     * @param string $academicYear
     * @throws Exception
     */
    public function reparentChild(int $grandChildId, int $newParentId, string $semester, string $academicYear): void
    {
        (new LessonCombination())->get()->where([
            'child_lesson_id' => $grandChildId,
            'semester'        => $semester,
            'academic_year'   => $academicYear,
        ])->update(['parent_lesson_id' => $newParentId]);
    }

    /**
     * Exam tipindeki bir child'ın alt child'larını (grandchild) yeni bir parent'a yeniden bağlar.
     * combineExamLesson sırasında kullanılır.
     *
     * @param int    $grandChildId
     * @param int    $newParentId
     * @param string $semester
     * @param string $academicYear
     * @throws Exception
     */
    public function reparentExamChild(int $grandChildId, int $newParentId, string $semester, string $academicYear): void
    {
        (new LessonCombination())->get()->where([
            'child_lesson_id' => $grandChildId,
            'type'            => 'exam',
            'semester'        => $semester,
            'academic_year'   => $academicYear,
        ])->update(['parent_lesson_id' => $newParentId]);
    }

    /**
     * Bir child dersin lesson tipindeki parent bağlantısını kaldırır.
     * deleteParentLesson işleminde kullanılır.
     *
     * @param int    $childId
     * @param string $semester
     * @param string $academicYear
     * @throws Exception
     */
    public function deleteLessonLink(int $childId, string $semester, string $academicYear): void
    {
        (new LessonCombination())->get()->where([
            'child_lesson_id' => $childId,
            'type'            => 'lesson',
            'semester'        => $semester,
            'academic_year'   => $academicYear,
        ])->delete();
    }

    /**
     * Bir child dersin exam tipindeki parent bağlantısını kaldırır.
     * deleteExamParentLesson işleminde kullanılır.
     *
     * @param int    $childId
     * @param string $semester
     * @param string $academicYear
     * @throws Exception
     */
    public function deleteExamLink(int $childId, string $semester, string $academicYear): void
    {
        (new LessonCombination())->get()->where([
            'child_lesson_id' => $childId,
            'type'            => 'exam',
            'semester'        => $semester,
            'academic_year'   => $academicYear,
        ])->delete();
    }

    /**
     * Bir dersi referans eden tüm kombinasyon kayıtlarını başka bir derse yönlendirir.
     * Merge işlemlerinde kaynak ders silinmeden önce çağrılmalıdır.
     * Hedef ders için zaten var olan (unique constraint çakışması yaratacak) satırlar atlanır.
     *
     * @param int $sourceLessonId  Taşınmak istenen (silinecek) dersin ID'si
     * @param int $targetLessonId  Kombinasyonların devredileceği dersin ID'si
     * @throws Exception
     */
    public function transferCombinations(int $sourceLessonId, int $targetLessonId): void
    {
        // parent_lesson_id olarak geçen satırlar
        $stmt = $this->db->prepare(
            "UPDATE lesson_combinations
                SET parent_lesson_id = :new_id
              WHERE parent_lesson_id = :old_id
                AND NOT EXISTS (
                    SELECT 1 FROM (SELECT * FROM lesson_combinations) AS lc2
                     WHERE lc2.parent_lesson_id = :new_id2
                       AND lc2.child_lesson_id  = lesson_combinations.child_lesson_id
                       AND lc2.type             = lesson_combinations.type
                       AND lc2.semester         = lesson_combinations.semester
                       AND lc2.academic_year    = lesson_combinations.academic_year
                )"
        );
        $stmt->execute([
            ':new_id'  => $targetLessonId,
            ':old_id'  => $sourceLessonId,
            ':new_id2' => $targetLessonId,
        ]);

        // child_lesson_id olarak geçen satırlar
        $stmt2 = $this->db->prepare(
            "UPDATE lesson_combinations
                SET child_lesson_id = :new_id
              WHERE child_lesson_id = :old_id
                AND NOT EXISTS (
                    SELECT 1 FROM (SELECT * FROM lesson_combinations) AS lc2
                     WHERE lc2.child_lesson_id  = :new_id2
                       AND lc2.parent_lesson_id = lesson_combinations.parent_lesson_id
                       AND lc2.type             = lesson_combinations.type
                       AND lc2.semester         = lesson_combinations.semester
                       AND lc2.academic_year    = lesson_combinations.academic_year
                )"
        );
        $stmt2->execute([
            ':new_id'  => $targetLessonId,
            ':old_id'  => $sourceLessonId,
            ':new_id2' => $targetLessonId,
        ]);
    }
}
