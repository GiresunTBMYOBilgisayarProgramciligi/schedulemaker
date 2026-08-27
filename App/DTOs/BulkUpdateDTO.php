<?php

namespace App\DTOs;

/**
 * Toplu güncelleme isteği verilerini taşıyan kesin tipli DTO.
 */
readonly class BulkUpdateDTO
{
    /**
     * @param int[] $ids Güncellenecek kayıt ID'leri
     * @param array<string, mixed> $fields Güncellenecek alanlar ve yeni değerleri
     */
    public function __construct(
        public array $ids = [],
        public array $fields = []
    ) {
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $ids = array_map('intval', (array)($data['ids'] ?? []));
        $fields = (array)($data['fields'] ?? []);
        return new self(ids: $ids, fields: $fields);
    }

    public function toArray(): array
    {
        return [
            'ids' => $this->ids,
            'fields' => $this->fields,
        ];
    }
}
