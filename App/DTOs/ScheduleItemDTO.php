<?php

namespace App\DTOs;

use App\Enums\ScheduleItemStatus;

/**
 * todo bunun adının DTO olması gerekmez mi? 
 * Schedule Item verisi için Data Transfer Object
 * 
 * Immutable, type-safe veri taşıyıcı
 */
readonly class ScheduleItemDTO
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
     * @param int|null $id ScheduleItem ID (özellikle silme işlemi için)
     */
    public function __construct(
        public int $scheduleId,
        public int $dayIndex,
        public int $weekIndex,
        public string $startTime,
        public string $endTime,
        public string $status,
        public ?array $data = null,
        public ?array $detail = null,
        public ?int $id = null
    ) {
    }

    /**
     * Array'den ScheduleItemDTO oluşturur
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheduleId: $data['schedule_id'] ?? 0,
            dayIndex: $data['day_index'] ?? 0,
            weekIndex: $data['week_index'] ?? 0,
            startTime: $data['start_time'] ?? '',
            endTime: $data['end_time'] ?? '',
            status: $data['status'] ?? 'single',
            data: $data['data'] ?? null,
            detail: $data['detail'] ?? null,
            id: isset($data['id']) ? (int) $data['id'] : null
        );
    }

    /**
     * DTO'yu array'e çevirir
     * @return array
     */
    public function toArray(): array
    {
        $arr = [
            'schedule_id' => $this->scheduleId,
            'day_index' => $this->dayIndex,
            'week_index' => $this->weekIndex,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'status' => $this->status,
            'data' => $this->data,
            'detail' => $this->detail
        ];
        if ($this->id !== null) {
            $arr['id'] = $this->id;
        }
        return $arr;
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
        return $this->status === ScheduleItemStatus::GROUP->value;
    }
}
