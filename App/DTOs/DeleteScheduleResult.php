<?php

namespace App\DTOs;

/**
 * Schedule Item Silme İşlemi Sonucu
 * 
 * Multi-schedule delete işlemlerinde silinen ve oluşturulan item'ları takip eder.
 * 
 * **Kullanım:**
 * ```php
 * // Basit silme
 * $result = DeleteScheduleResult::success([45, 46, 47, 48]);
 * 
 * // Partial delete (yeni item'lar oluşturuldu)
 * $result = DeleteScheduleResult::success([45, 46], [101, 102]);
 * 
 * // Hata
 * $result = DeleteScheduleResult::failure("Item bulunamadı");
 * ```
 */
class DeleteScheduleResult
{
    public bool $success;

    /** @var array Silinen schedule item ID'leri */
    public array $deletedIds;

    /** @var array Partial delete sonucu oluşturulan item ID'leri */
    public array $createdIds;

    /** @var array Hata mesajları */
    public array $errors;

    /** @var int Toplam silinen item sayısı */
    public int $totalDeleted;

    /** @var int Toplam oluşturulan item sayısı */
    public int $totalCreated;

    /**
     * Başarılı silme sonucu
     * 
     * @param array $deletedIds Silinen item ID'leri
     * @param array $createdIds Oluşturulan item ID'leri (partial delete için)
     * @return self
     */
    public static function success(
        array $deletedIds,
        array $createdIds = []
    ): self {
        $result = new self();
        $result->success = true;
        $result->deletedIds = array_values(array_unique($deletedIds));
        $result->createdIds = array_values(array_unique($createdIds));
        $result->errors = [];
        $result->totalDeleted = count($result->deletedIds);
        $result->totalCreated = count($result->createdIds);
        return $result;
    }

    /**
     * Hatalı silme sonucu
     * 
     * @param string $error Hata mesajı
     * @return self
     */
    public static function failure(string $error): self
    {
        $result = new self();
        $result->success = false;
        $result->deletedIds = [];
        $result->createdIds = [];
        $result->errors = [$error];
        $result->totalDeleted = 0;
        $result->totalCreated = 0;
        return $result;
    }

    /**
     * Frontend için array formatında döner
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'status' => $this->success ? 'success' : 'error',
            'deletedIds' => $this->deletedIds,
            'createdItems' => $this->createdIds,
            'errors' => $this->errors,
            'totalDeleted' => $this->totalDeleted,
            'totalCreated' => $this->totalCreated
        ];
    }
}
