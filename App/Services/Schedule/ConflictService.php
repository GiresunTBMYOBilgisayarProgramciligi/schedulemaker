<?php

namespace App\Services\Schedule;

use App\Services\BaseService;
use App\Models\Lesson;
use App\Models\Schedule;
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
        $status = $itemData['status'] ?? 'single';
        $isDummy = in_array($status, ['preferred', 'unavailable']);

        $targetSchedule = (new Schedule())->find($itemData['schedule_id']);
        if (!$targetSchedule) {
            throw new Exception("Hedef Program bulunamadı");
        }

        if (!$isDummy) {
            // Data parse + validation
            $data = $itemData['data'] ?? [];
            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
                throw new Exception("Geçersiz data formatı - array of objects bekleniyor");
            }

            $lessonId = isset($data[0]['lesson_id']) && $data[0]['lesson_id'] !== '' ? (int)$data[0]['lesson_id'] : null;
            $lecturerId = isset($data[0]['lecturer_id']) && $data[0]['lecturer_id'] !== '' ? (int)$data[0]['lecturer_id'] : null;
            $classroomId = isset($data[0]['classroom_id']) && $data[0]['classroom_id'] !== '' ? (int)$data[0]['classroom_id'] : null;

            if (!$lessonId) {
                throw new Exception("lesson_id bulunamadı");
            }

            $lesson = (new Lesson())->where(['id' => $lessonId])->with(['childLessons'])->first();
            if (!$lesson) {
                throw new Exception("Ders bulunamadı");
            }

            $owners = $this->determineOwners($itemData, $lesson, $lecturerId, $classroomId);
        } else {
            $lesson = null;
            $owners = [
                [
                    'type' => $targetSchedule->owner_type,
                    'id' => $targetSchedule->owner_id,
                    'lesson_context' => null
                ]
            ];
        }

        $conflictResolver = new ConflictResolver();
        $conflictErrors = $conflictResolver->checkConflicts($itemData, $owners, $targetSchedule, $lesson);

        $errors = array_merge($errors, $conflictErrors);
    }

    /**
     * Item için owner listesini belirler.
     *
     * - Eğer item'ın detail.assignments değeri varsa → sınav item'ı (gözetmen+derslik owner'ları)
     * - Yoksa → normal ders item'ı (hoca+derslik+program+ders owner'ları)
     *
     * @param array               $itemData    Item verisi
     * @param Lesson              $lesson      İlgili ders
     * @param int|string|null     $lecturerId  Hoca ID (ders için)
     * @param int|string|null     $classroomId Derslik ID (ders için)
     * @return array Owner listesi [['type' => 'user|classroom|program|lesson', 'id' => int], ...]
     */
    private function determineOwners(
        array $itemData,
        Lesson $lesson,
        int|string|null $lecturerId = null,
        int|string|null $classroomId = null
    ): array {
        $lecturerId = ($lecturerId !== null && $lecturerId !== '') ? (int)$lecturerId : null;
        $classroomId = ($classroomId !== null && $classroomId !== '') ? (int)$classroomId : null;

        $owners = [];
        $examAssignments = $itemData['detail']['assignments'] ?? null;

        if ($examAssignments) {
            // Sınav → program + ders + her atama için gözetmen ve derslik
            $owners[] = [
                'type' => 'program',
                'id' => $lesson->program_id,
                'semester_no' => $lesson->semester_no,
                'lesson_context' => $lesson
            ];
            $owners[] = [
                'type' => 'lesson',
                'id' => $lesson->id,
                'lesson_context' => $lesson
            ];

            foreach ($examAssignments as $assignment) {
                if (!empty($assignment['classroom_id'])) {
                    $owners[] = [
                        'type' => 'classroom',
                        'id' => (int)$assignment['classroom_id'],
                        'lesson_context' => $lesson
                    ];
                }
                if (!empty($assignment['observer_id'])) {
                    $owners[] = [
                        'type' => 'user',
                        'id' => (int)$assignment['observer_id'],
                        'lesson_context' => $lesson
                    ];
                }
            }

            // Sınav programında aynı koda sahip diğer grupları da dahil et (Kullanıcı Talebi: Tek ders olarak işleme girme)
            if ($lesson->group_no > 0) {
                $siblings = (new Lesson())->get()->where([
                    'code' => $lesson->code,
                    'program_id' => $lesson->program_id,
                    'semester_no' => $lesson->semester_no,
                    'group_no' => ['>' => 0],
                    'id' => ['!=' => $lesson->id]
                ])->all();


                foreach ($siblings as $sibling) {
                    $owners[] = [
                        'type' => 'program',
                        'id' => $sibling->program_id,
                        'semester_no' => $sibling->semester_no,
                        'lesson_context' => $sibling
                    ];
                    $owners[] = [
                        'type' => 'lesson',
                        'id' => $sibling->id,
                        'lesson_context' => $sibling
                    ];
                }
            }
        } else {
            // Normal ders → hoca + derslik + program + ders
            $owners = [
                [
                    'type' => 'user',
                    'id' => $lecturerId,
                    'lesson_context' => $lesson
                ],
                [
                    'type' => 'classroom',
                    'id' => ($lesson->classroom_type == 3) ? null : $classroomId,
                    'lesson_context' => $lesson
                ],
                [
                    'type' => 'program',
                    'id' => $lesson->program_id,
                    'semester_no' => $lesson->semester_no,
                    'lesson_context' => $lesson
                ],
                [
                    'type' => 'lesson',
                    'id' => $lesson->id,
                    'lesson_context' => $lesson
                ],
            ];
        }

        // Child lesson'lar için de owner ekle
        if (!empty($lesson->childLessons)) {
            foreach ($lesson->childLessons as $childLesson) {
                $owners[] = [
                    'type' => 'lesson',
                    'id' => $childLesson->id,
                    'lesson_context' => $childLesson
                ];
                if ($childLesson->program_id) {
                    $owners[] = [
                        'type' => 'program',
                        'id' => $childLesson->program_id,
                        'semester_no' => $childLesson->semester_no,
                        'lesson_context' => $childLesson
                    ];
                }
            }
        }

        return $owners;
    }
}
