<?php

namespace App\DTOs;

/**
 * Program öğesi kilitleme/kilit açma verisi için kesin tipli DTO.
 */
readonly class ToggleLockScheduleItemDTO
{
    /**
     * @param int[] $ids
     * @param bool|null $target_state
     */
    public function __construct(
        public array $ids = [],
        public ?bool $target_state = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $ids = [];
        if (!empty($data['ids'])) {
            $ids = is_string($data['ids']) ? (json_decode($data['ids'], true) ?: []) : (array)$data['ids'];
        } elseif (!empty($data['id'])) {
            $ids = [(int)$data['id']];
        }

        $ids = array_map('intval', $ids);
        $targetState = isset($data['target_state']) ? (bool)$data['target_state'] : null;

        return new self(ids: $ids, target_state: $targetState);
    }

    public function toArray(): array
    {
        return [
            'ids'          => $this->ids,
            'target_state' => $this->target_state,
        ];
    }
}
