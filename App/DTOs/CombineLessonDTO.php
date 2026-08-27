<?php

namespace App\DTOs;

use function App\Helpers\getSettingValue;

/**
 * Ders birleştirme verisi için kesin tipli DTO.
 */
readonly class CombineLessonDTO
{
    /**
     * @param int $parentId
     * @param int $childId
     * @param array $itemsToRemove
     * @param string $semester
     * @param string $academicYear
     */
    public function __construct(
        public int $parentId,
        public int $childId,
        public array $itemsToRemove = [],
        public string $semester = '',
        public string $academicYear = ''
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            parentId: (int) ($data['parent_lesson_id'] ?? 0),
            childId: (int) ($data['child_lesson_id'] ?? 0),
            itemsToRemove: (array) ($data['items_to_remove'] ?? []),
            semester: (string) ($data['semester'] ?? getSettingValue('semester')),
            academicYear: (string) ($data['academic_year'] ?? getSettingValue('academic_year'))
        );
    }

    /**
     * items_to_remove dizisini parse ederek array'e dönüştürür
     * Örn: ["606_2", "606_3"] → [606 => [2, 3]]
     *
     * @return array<int, int[]>
     */
    public function getParsedItemsToRemove(): array
    {
        $slotsToSkip = [];
        foreach ($this->itemsToRemove as $entry) {
            $parts = explode('_', (string) $entry, 2);
            if (count($parts) === 2) {
                [$itemId, $slotIdx] = $parts;
                $slotsToSkip[(int)$itemId][] = (int)$slotIdx;
            }
        }
        return $slotsToSkip;
    }

    public function toArray(): array
    {
        return [
            'parent_lesson_id' => $this->parentId,
            'child_lesson_id'  => $this->childId,
            'items_to_remove'  => $this->itemsToRemove,
            'semester'         => $this->semester,
            'academic_year'    => $this->academicYear,
        ];
    }
}
