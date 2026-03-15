<?php

namespace App\DTOs;

/**
 * Schedule kaydetme işleminin sonucu
 * 
 * Oluşturulan item ID'lerini ve istatistikleri içerir
 */
readonly class SaveScheduleResult
{
    /**
     * @param array $createdIds Oluşturulan schedule item ID'leri
     * @param int $totalProcessed Toplam işlenen item sayısı
     * @param array $warnings Uyarı mesajları (varsa)
     */
    public function __construct(
        public array $createdIds = [],
        public int $totalProcessed = 0,
        public array $warnings = [],
        public bool $success = true
    ) {
    }

    /**
     * Başarılı sonuç oluşturur
     * @param array $createdIds
     * @param int $totalProcessed
     * @return self
     */
    public static function success(array $createdIds, int $totalProcessed): self
    {
        return new self(
            createdIds: $createdIds,
            totalProcessed: $totalProcessed
        );
    }

    /**
     * Uyarılı sonuç oluşturur
     * @param array $createdIds
     * @param int $totalProcessed
     * @param array $warnings
     * @return self
     */
    public static function withWarnings(array $createdIds, int $totalProcessed, array $warnings): self
    {
        return new self(
            createdIds: $createdIds,
            totalProcessed: $totalProcessed,
            warnings: $warnings
        );
    }

    /**
     * Sonucu array formatında döner
     * @return array
     */
    public function toArray(): array
    {
        return [
            'created_ids' => $this->createdIds,
            'total_processed' => $this->totalProcessed,
            'warnings' => $this->warnings,
            'success' => true
        ];
    }

    /**
     * Uyarı var mı?
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
}
