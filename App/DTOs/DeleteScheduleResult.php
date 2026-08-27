<?php

namespace App\DTOs;

/**
 * Schedule Item Silme İşlemi Sonucu DTO
 */
readonly class DeleteScheduleResult
{
    /**
     * @param bool $success İşlem başarılı mı?
     * @param int[] $deletedIds Silinen schedule item ID'leri
     * @param int[] $createdIds Partial delete sonucu oluşturulan item ID'leri
     * @param string[] $errors Hata mesajları
     * @param int $totalDeleted Toplam silinen item sayısı
     * @param int $totalCreated Toplam oluşturulan item sayısı
     */
    public function __construct(
        public bool $success = true,
        public array $deletedIds = [],
        public array $createdIds = [],
        public array $errors = [],
        public int $totalDeleted = 0,
        public int $totalCreated = 0
    ) {
    }

    /**
     * Başarılı silme sonucu
     * 
     * @param int[] $deletedIds Silinen item ID'leri
     * @param int[] $createdIds Oluşturulan item ID'leri (partial delete için)
     * @return self
     */
    public static function success(
        array $deletedIds,
        array $createdIds = []
    ): self {
        $uniqueDeleted = array_values(array_unique($deletedIds));
        $uniqueCreated = array_values(array_unique($createdIds));

        return new self(
            success: true,
            deletedIds: $uniqueDeleted,
            createdIds: $uniqueCreated,
            errors: [],
            totalDeleted: count($uniqueDeleted),
            totalCreated: count($uniqueCreated)
        );
    }

    /**
     * Hatalı silme sonucu
     * 
     * @param string $error Hata mesajı
     * @return self
     */
    public static function failure(string $error): self
    {
        return new self(
            success: false,
            deletedIds: [],
            createdIds: [],
            errors: [$error],
            totalDeleted: 0,
            totalCreated: 0
        );
    }

    /**
     * Frontend için array formatında döner
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'status'       => $this->success ? 'success' : 'error',
            'deletedIds'   => $this->deletedIds,
            'createdItems' => $this->createdIds,
            'errors'       => $this->errors,
            'totalDeleted' => $this->totalDeleted,
            'totalCreated' => $this->totalCreated,
        ];
    }
}
