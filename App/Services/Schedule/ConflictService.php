<?php

namespace App\Services\Schedule;

use App\Services\BaseService;
use App\Models\Lesson;
use App\Models\Schedule;
use App\DTOs\ConflictFilterDTO;
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
     * @param ConflictFilterDTO|array $filters
     * @return bool Çakışma yoksa true döner; varsa Exception fırlatır
     * @throws Exception
     */
    public function checkScheduleCrash(ConflictFilterDTO|array $filters = []): bool
    {
        $dto = $filters instanceof ConflictFilterDTO ? $filters : ConflictFilterDTO::fromArray($filters);

        $items = is_string($dto->items) 
            ? json_decode($dto->items, true) 
            : (is_array($dto->items) ? $dto->items : []);

        if (is_string($dto->items) && json_last_error() !== JSON_ERROR_NONE) {
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
                    'type'           => $targetSchedule->owner_type,
                    'id'             => $targetSchedule->owner_id,
                    'lesson_context' => null
                ]
            ];
        }

        $conflictResolver = new ConflictResolver();
        $conflicts = $conflictResolver->checkConflicts(
            $itemData,
            $owners,
            $targetSchedule,
            $lesson
        );

        if (!empty($conflicts)) {
            $resolved = $conflictResolver->resolveConflict($conflicts, $status, $targetSchedule);
            if ($resolved['action'] === 'error') {
                $errors[] = $resolved['message'];
            }
        }
    }

    /**
     * Item için çakışma kontrolü yapılacak tüm kaynakları (hoca, derslik, program, çocuk dersler) belirler.
     *
     * @param array $itemData
     * @param Lesson $lesson
     * @param int|null $lecturerId
     * @param int|null $classroomId
     * @return array
     */
    private function determineOwners(array $itemData, Lesson $lesson, ?int $lecturerId, ?int $classroomId): array
    {
        $owners = [];

        // Hoca
        if ($lecturerId) {
            $owners[] = [
                'type'           => 'user',
                'id'             => $lecturerId,
                'lesson_context' => $lesson
            ];
        }

        // Derslik
        if ($classroomId) {
            $owners[] = [
                'type'           => 'classroom',
                'id'             => $classroomId,
                'lesson_context' => $lesson
            ];
        }

        // Program
        if ($lesson->program_id) {
            $owners[] = [
                'type'           => 'program',
                'id'             => $lesson->program_id,
                'lesson_context' => $lesson
            ];
        }

        // Çocuk dersler
        if (!empty($lesson->childLessons)) {
            foreach ($lesson->childLessons as $childLesson) {
                if ($childLesson->program_id) {
                    $owners[] = [
                        'type'           => 'program',
                        'id'             => $childLesson->program_id,
                        'lesson_context' => $childLesson
                    ];
                }
            }
        }

        return $owners;
    }
}
