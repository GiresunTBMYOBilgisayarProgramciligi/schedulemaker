<?php

namespace App\DTOs;

/**
 * Schedule Item verisi için Data Transfer Object
 * 
 * Immutable, type-safe veri taşıyıcı
 */
readonly class ScheduleItemData
{
    /**
     * @param int $scheduleId Schedule ID
     * @param int $dayIndex Gün indeksi (0-6)
     * @param int $weekIndex Hafta indeksi (0+)
     * @param string $startTime Başlangıç saati (HH:MM)
     * @param string $endTime Bitiş saati (HH:MM)
     * @param string $status Item durumu (single, group, preferred, unavailable)
     * @param array|null $data Ders verileri (lesson_id, lecturer_id, classroom_id vb.)
     * @param array|null $detail Detay verisi
     */
    public function __construct(
        public int $scheduleId,
        public int $dayIndex,
        public int $weekIndex,
        public string $startTime,
        public string $endTime,
        public string $status,
        public ?array $data = null,
        public ?array $detail = null
    ) {
    }

    /**
     * Array'den ScheduleItemData oluşturur
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheduleId: $data['schedule_id'],
            dayIndex: $data['day_index'],
            weekIndex: $data['week_index'] ?? 0,
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            status: $data['status'],
            data: $data['data'] ?? null,
            detail: $data['detail'] ?? null
        );
    }

    /**
     * DTO'yu array'e çevirir
     * @return array
     */
    public function toArray(): array
    {
        return [
            'schedule_id' => $this->scheduleId,
            'day_index' => $this->dayIndex,
            'week_index' => $this->weekIndex,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'status' => $this->status,
            'data' => $this->data,
            'detail' => $this->detail
        ];
    }

    /**
     * Dummy item mi? (preferred veya unavailable)
     * @return bool
     */
    public function isDummy(): bool
    {
        return in_array($this->status, ['preferred', 'unavailable']);
    }

    /**
     * Group item mi?
     * @return bool
     */
    public function isGroup(): bool
    {
        return $this->status === 'group';
    }
}
