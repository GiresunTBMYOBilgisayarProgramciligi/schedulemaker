<?php

namespace App\DTOs;

class BulkUpdateDTO
{
    /** @var int[] Güncellenecek kayıt ID'leri */
    public array $ids;

    /** @var array<string, mixed> Güncellenecek alanlar (alan_adı => yeni_değer) */
    public array $fields;

    /**
     * @param int[] $ids
     * @param array<string, mixed> $fields
     */
    public function __construct(array $ids, array $fields)
    {
        $this->ids = $ids;
        $this->fields = $fields;
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $ids = array_map('intval', $data['ids'] ?? []);
        $fields = $data['fields'] ?? [];

        return new self($ids, $fields);
    }
}
