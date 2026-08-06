<?php

namespace App\DTOs;

class BulkDeleteDTO
{
    /** @var int[] Silinecek kayıt ID'leri */
    public array $ids;

    /**
     * @param int[] $ids
     */
    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $ids = array_map('intval', $data['ids'] ?? []);
        return new self($ids);
    }
}
