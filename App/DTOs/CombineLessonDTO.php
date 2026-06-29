<?php

namespace App\DTOs;

class CombineLessonDTO
{
    public int $parentId;
    public int $childId;
    public array $itemsToRemove;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->parentId = (int) ($data['parent_lesson_id'] ?? 0);
        $dto->childId = (int) ($data['child_lesson_id'] ?? 0);
        $dto->itemsToRemove = (array) ($data['items_to_remove'] ?? []);
        return $dto;
    }

    /**
     * items_to_remove dizisini parse ederek array'e dönüştürür
     * Örn: ["606_2", "606_3"] → [606 => [2, 3]]
     */
    public function getParsedItemsToRemove(): array
    {
        $slotsToSkip = [];
        foreach ($this->itemsToRemove as $entry) {
            [$itemId, $slotIdx] = explode('_', (string) $entry, 2);
            $slotsToSkip[(int)$itemId][] = (int)$slotIdx;
        }
        return $slotsToSkip;
    }
}
