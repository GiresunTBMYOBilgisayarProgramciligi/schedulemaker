<?php

namespace App\DTOs;

/**
 * Toplu silme isteği verilerini taşıyan kesin tipli DTO.
 */
readonly class BulkDeleteDTO
{
    /**
     * @param int[] $ids Silinecek kayıt ID'leri
     */
    public function __construct(
        public array $ids = []
    ) {
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $ids = array_map('intval', (array)($data['ids'] ?? []));
        return new self(ids: $ids);
    }

    public function toArray(): array
    {
        return [
            'ids' => $this->ids,
        ];
    }
}
