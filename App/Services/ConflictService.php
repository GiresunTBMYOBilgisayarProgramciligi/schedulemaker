<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use Exception;

/**
 * Ders ve Sınav programlarında çakışma kontrol servisi.
 *
 * Hem ders hem de sınav tipi programlarda ortak olarak kullanılır.
 * İç mantık, schedule item'ının verisine (assignments, schedule_type) göre
 * ders ve sınav arasında ayrım yapar.
 */
class ConflictService extends BaseService
{
    /**
     * Programa eklenmek istenen item(lar) için çakışma kontrolü yapar.
     *
     * @param array $filters ['items' => JSON string, ...]
     * @return bool Çakışma yoksa true döner; varsa Exception fırlatır
     * @throws Exception
     */
    public function checkScheduleCrash(array $filters = []): bool
    {
        $items = json_decode($filters['items'] ?? '[]', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Geçersiz JSON verisi");
        }

        $errors = [];
        foreach ($items as $itemData) {
            $this->checkItemConflict($itemData, $errors);
        }

        if (!empty($errors)) {
            $errors = array_unique($errors);
            throw new Exception(implode("\n", $errors));
        }

        return true;
    }

    /**
     * Tek bir item için çakışma kontrolü yapar.
     * ConflictResolver'a delege eder; ders/sınav ayrımını ConflictResolver yapar.
     *
     * @param array $itemData Item verisi
     * @param array $errors Hata mesajları (referans ile)
     * @throws Exception
     */
    private function checkItemConflict(array $itemData, array &$errors = []): void
    {
        // Data parse + validation
        $data = $itemData['data'];
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
            throw new Exception("Geçersiz data formatı - array of objects bekleniyor");
        }

        $lessonId = $data[0]['lesson_id'] ?? null;
        $lecturerId = $data[0]['lecturer_id'] ?? null;
        $classroomId = $data[0]['classroom_id'] ?? null;

        if (!$lessonId) {
            throw new Exception("lesson_id bulunamadı");
        }

        $lesson = (new Lesson())->where(['id' => $lessonId])->with(['childLessons'])->first();
        if (!$lesson) {
            throw new Exception("Ders bulunamadı");
        }

        $owners = $this->determineOwners($itemData, $lesson, $lecturerId, $classroomId);

        $targetSchedule = (new Schedule())->find($itemData['schedule_id']);
        if (!$targetSchedule) {
            throw new Exception("Hedef Program bulunamadı");
        }

        $conflictResolver = new \App\Services\Helpers\ConflictResolver();
        $conflictErrors = $conflictResolver->checkConflicts($itemData, $owners, $targetSchedule, $lesson);

        $errors = array_merge($errors, $conflictErrors);
    }

    /**
     * Item için owner listesini belirler.
     *
     * - Eğer item'ın detail.assignments değeri varsa → sınav item'ı (gözetmen+derslik owner'ları)
     * - Yoksa → normal ders item'ı (hoca+derslik+program+ders owner'ları)
     *
     * @param array    $itemData    Item verisi
     * @param Lesson   $lesson      İlgili ders
     * @param int|null $lecturerId  Hoca ID (ders için)
     * @param int|null $classroomId Derslik ID (ders için)
     * @return array Owner listesi [['type' => 'user|classroom|program|lesson', 'id' => int], ...]
     */
    private function determineOwners(
        array $itemData,
        Lesson $lesson,
        ?int $lecturerId,
        ?int $classroomId
    ): array {
        $owners = [];
        $examAssignments = $itemData['detail']['assignments'] ?? null;

        if ($examAssignments) {
            // Sınav → program + ders + her atama için gözetmen ve derslik
            $owners[] = ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no];
            $owners[] = ['type' => 'lesson', 'id' => $lesson->id];

            foreach ($examAssignments as $assignment) {
                $owners[] = ['type' => 'classroom', 'id' => $assignment['classroom_id']];
                $owners[] = ['type' => 'user', 'id' => $assignment['observer_id']];
            }
        } else {
            // Normal ders → hoca + derslik + program + ders
            $owners = [
                ['type' => 'user', 'id' => $lecturerId],
                ['type' => 'classroom', 'id' => ($lesson->classroom_type == 3) ? null : $classroomId],
                ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no],
                ['type' => 'lesson', 'id' => $lesson->id],
            ];
        }

        // Child lesson'lar için de owner ekle
        if (!empty($lesson->childLessons)) {
            foreach ($lesson->childLessons as $childLesson) {
                $owners[] = ['type' => 'lesson', 'id' => $childLesson->id];
                if ($childLesson->program_id) {
                    $owners[] = ['type' => 'program', 'id' => $childLesson->program_id, 'semester_no' => $childLesson->semester_no];
                }
            }
        }

        return $owners;
    }

    /**
     * İki zaman aralığının çakışıp çakışmadığını kontrol eder.
     * Mantık: (Start1 < End2) && (Start2 < End1)
     *
     * @param string $start1 H:i
     * @param string $end1   H:i
     * @param string $start2 H:i
     * @param string $end2   H:i
     * @return bool Çakışma varsa true
     */
    public function checkTimeOverlap(
        string $start1,
        string $end1,
        string $start2,
        string $end2
    ): bool {
        $s1 = substr($start1, 0, 5);
        $e1 = substr($end1, 0, 5);
        $s2 = substr($start2, 0, 5);
        $e2 = substr($end2, 0, 5);

        return ($s1 < $e2) && ($s2 < $e1);
    }
}
