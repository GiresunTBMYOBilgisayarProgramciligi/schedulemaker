<?php

namespace App\DTOs;

/**
 * Toplu işlem (bulk delete, bulk update) sonuçlarını temsil eden kesin tipli DTO.
 */
readonly class BulkActionResultDTO
{
    /**
     * @param int[] $success Başarıyla işlenen ID'ler
     * @param array<int, string> $failed İşlenemeyen ID'ler ve hata mesajları [id => message]
     */
    public function __construct(
        public array $success = [],
        public array $failed = []
    ) {
    }

    public static function successOnly(array $success): self
    {
        return new self(success: $success, failed: []);
    }

    public static function failureOnly(array $failed): self
    {
        return new self(success: [], failed: $failed);
    }

    public function isAllSuccessful(): bool
    {
        return empty($this->failed) && !empty($this->success);
    }

    public function isAllFailed(): bool
    {
        return !empty($this->failed) && empty($this->success);
    }

    public function getSuccessCount(): int
    {
        return count($this->success);
    }

    public function getFailedCount(): int
    {
        return count($this->failed);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'failed'  => $this->failed,
        ];
    }
}
