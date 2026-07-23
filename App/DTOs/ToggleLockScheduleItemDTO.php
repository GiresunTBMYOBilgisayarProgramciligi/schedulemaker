<?php

namespace App\DTOs;

class ToggleLockScheduleItemDTO
{
    /**
     * @param int[] $ids
     * @param bool|null $target_state
     */
    public function __construct(
        public readonly array $ids,
        public readonly ?bool $target_state = null
    ) {}

    public static function fromArray(array $data): self
    {
        $ids = [];
        if (!empty($data['ids'])) {
            $ids = is_string($data['ids']) ? json_decode($data['ids'], true) : $data['ids'];
        } elseif (!empty($data['id'])) {
            $ids = [(int)$data['id']];
        }

        $targetState = isset($data['target_state']) ? (bool)$data['target_state'] : null;

        return new self($ids, $targetState);
    }
}
