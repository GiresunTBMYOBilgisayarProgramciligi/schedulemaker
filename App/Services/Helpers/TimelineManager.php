<?php

namespace App\Services\Helpers;

use function getSettingValue;

/**
 * Timeline yönetimi için helper service
 * 
 * Zaman aralıkları üzerinde işlemler yapar:
 * - Timeline flattening (çakışan aralıkları birleştirme)
 * - Saat karşılaştırmaları
 * - Slot hesaplamaları
 */
class TimelineManager
{
    /**
     * Verilen schedule items'ı flatten eder (çakışan saatleri birleştirir)
     * Slot-based approach: Her dakika yerine ders slotları (duration + break) kullanır
     * 
     * @param array $items Mevcut schedule items
     * @param string $newStart Yeni item başlangıç saati (HH:MM)
     * @param string $newEnd Yeni item bitiş saati (HH:MM)
     * @param string $scheduleType Schedule tipi ('lesson', 'midterm-exam', etc.)
     * @return array Flatten edilmiş timeline ve çakışma bilgisi
     */
    public function flattenTimeline(array $items, string $newStart, string $newEnd, string $scheduleType = 'lesson'): array
    {
        // Sistem ayarlarından ders süresi ve tenefüs süresini al
        $group = ($scheduleType === 'lesson') ? 'lesson' : 'exam';
        $duration = (int) getSettingValue('duration', $group, 50);
        $break = (int) getSettingValue('break', $group, 10);
        $slotSize = $duration + $break;

        // Gün başlangıç ve bitiş saatlerini al
        $dayStart = getSettingValue('day_start', $group, '08:00');
        $dayEnd = getSettingValue('day_end', $group, '17:00');

        $dayStartMinute = $this->timeToMinutes($dayStart);
        $dayEndMinute = $this->timeToMinutes($dayEnd);
        $totalSlots = (int) ceil(($dayEndMinute - $dayStartMinute) / $slotSize);

        // Slot bazlı timeline: Her slot için status ve items
        $timeline = [];
        for ($s = 0; $s < $totalSlots; $s++) {
            $slotStartTime = $dayStartMinute + ($s * $slotSize);
            $timeline[$s] = [
                'slot_index' => $s,
                'start_time' => $this->minutesToTime($slotStartTime),
                'end_time' => $this->minutesToTime(min($slotStartTime + $duration, $dayEndMinute)),
                'status' => 'free',
                'items' => []
            ];
        }

        // Mevcut itemları timeline'a işle
        foreach ($items as $item) {
            $startMinute = $this->timeToMinutes($item->start_time);
            $endMinute = $this->timeToMinutes($item->end_time);

            // Item'ın hangi slot'ları kapsadığını bul
            $startSlot = (int) floor(($startMinute - $dayStartMinute) / $slotSize);
            $endSlot = (int) ceil(($endMinute - $dayStartMinute) / $slotSize);

            for ($s = $startSlot; $s < $endSlot && $s < $totalSlots; $s++) {
                if ($s >= 0) {
                    $timeline[$s]['status'] = $item->status ?? 'busy';
                    $timeline[$s]['items'][] = $item;
                }
            }
        }

        // Yeni item'ın slot'larını kontrol et
        $newStartMinute = $this->timeToMinutes($newStart);
        $newEndMinute = $this->timeToMinutes($newEnd);
        $newStartSlot = (int) floor(($newStartMinute - $dayStartMinute) / $slotSize);
        $newEndSlot = (int) ceil(($newEndMinute - $dayStartMinute) / $slotSize);

        $conflicts = [];
        $freeSlots = 0;
        $busySlots = 0;

        for ($s = $newStartSlot; $s < $newEndSlot && $s < $totalSlots; $s++) {
            if ($s >= 0) {
                if ($timeline[$s]['status'] === 'free') {
                    $freeSlots++;
                } else {
                    $busySlots++;
                    // Çakışan itemları topla
                    foreach ($timeline[$s]['items'] as $conflictItem) {
                        $conflicts[$conflictItem->id] = $conflictItem;
                    }
                }
            }
        }

        return [
            'timeline' => $timeline,
            'conflicts' => array_values($conflicts),
            'total_slots' => $newEndSlot - $newStartSlot,
            'free_slots' => $freeSlots,
            'busy_slots' => $busySlots,
            'is_fully_free' => $busySlots === 0,
            'slot_size_minutes' => $slotSize,
            'duration_minutes' => $duration,
            'break_minutes' => $break
        ];
    }

    /**
     * Belirli bir saatin verilen aralıkta olup olmadığını kontrol eder
     * 
     * @param string $time Kontrol edilecek saat (HH:MM)
     * @param string $start Aralık başlangıcı (HH:MM)
     * @param string $end Aralık bitişi (HH:MM)
     * @return bool Saat aralıkta ise true
     */
    public function timeInRange(string $time, string $start, string $end): bool
    {
        $timeMinutes = $this->timeToMinutes($time);
        $startMinutes = $this->timeToMinutes($start);
        $endMinutes = $this->timeToMinutes($end);

        return $timeMinutes >= $startMinutes && $timeMinutes < $endMinutes;
    }

    /**
     * İki zaman aralığının çakışıp çakışmadığını kontrol eder
     * 
     * @param string $start1 Birinci aralık başlangıç
     * @param string $end1 Birinci aralık bitiş
     * @param string $start2 İkinci aralık başlangıç
     * @param string $end2 İkinci aralık bitiş
     * @return bool Çakışma varsa true
     */
    public function checkOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return ($start1 < $end2) && ($end1 > $start2);
    }

    /**
     * İki saat arasındaki süreyi dakika cinsinden hesaplar
     * 
     * @param string $start Başlangıç saati (HH:MM)
     * @param string $end Bitiş saati (HH:MM)
     * @return int Süre (dakika)
     */
    public function getDurationInMinutes(string $start, string $end): int
    {
        $startMinutes = $this->timeToMinutes($start);
        $endMinutes = $this->timeToMinutes($end);

        return $endMinutes - $startMinutes;
    }

    /**
     * İki saat arasındaki süreyi saat cinsinden hesaplar
     * 
     * @param string $start Başlangıç saati (HH:MM)
     * @param string $end Bitiş saati (HH:MM)
     * @return float Süre (saat, ondalıklı)
     */
    public function getDurationInHours(string $start, string $end): float
    {
        $minutes = $this->getDurationInMinutes($start, $end);
        return $minutes / 60;
    }

    /**
     * Saat string'ini dakikaya çevirir (00:00'dan itibaren)
     * 
     * @param string $time Saat (HH:MM formatında)
     * @return int Dakika (0-1439)
     */
    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);
        return (int) $hours * 60 + (int) $minutes;
    }

    /**
     * Dakikayı saat string'ine çevirir
     * 
     * @param int $minutes Dakika (0-1439)
     * @return string Saat (HH:MM formatında)
     */
    public function minutesToTime(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Birden fazla schedule item'ı birleştirip toplam süreyi hesaplar
     * 
     * @param array $items Schedule items
     * @return array ['total_hours' => float, 'total_minutes' => int]
     */
    public function calculateTotalDuration(array $items): array
    {
        $totalMinutes = 0;

        foreach ($items as $item) {
            $duration = $this->getDurationInMinutes($item->start_time, $item->end_time);
            $totalMinutes += $duration;
        }

        return [
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2)
        ];
    }

    /**
     * Verilen gün için boş zaman aralıklarını bulur
     * 
     * @param array $items Gün içindeki schedule items
     * @param string $scheduleType Schedule tipi ('lesson', 'midterm-exam', 'final-exam', 'makeup-exam')
     * @return array Boş aralıklar [['start' => '10:00', 'end' => '11:00'], ...]
     */
    public function findFreeSlots(array $items, string $scheduleType = 'lesson'): array
    {
        // Sistem ayarlarından gün başlangıç ve bitiş saatlerini al
        $group = ($scheduleType === 'lesson') ? 'lesson' : 'exam';
        $dayStart = getSettingValue('day_start', $group, '08:00');
        $dayEnd = getSettingValue('day_end', $group, '17:00');

        $dayStartMinute = $this->timeToMinutes($dayStart);
        $dayEndMinute = $this->timeToMinutes($dayEnd);

        // Sistem ayarlarından ders süresi ve tenefüs süresini al
        $duration = (int) getSettingValue('duration', $group, 50);
        $break = (int) getSettingValue('break', $group, 10);
        $slotSize = $duration + $break; // Bir slot = ders + tenefüs

        // Slot bazlı timeline oluştur (her slot bir ders)
        $totalSlots = (int) ceil(($dayEndMinute - $dayStartMinute) / $slotSize);
        $timeline = array_fill(0, $totalSlots, false); // false = boş, true = dolu

        // Dolu alanları işaretle
        foreach ($items as $item) {
            $startMinute = $this->timeToMinutes($item->start_time);
            $endMinute = $this->timeToMinutes($item->end_time);

            // Item'ın hangi slot'ları kapsadığını bul
            $startSlot = (int) floor(($startMinute - $dayStartMinute) / $slotSize);
            $endSlot = (int) ceil(($endMinute - $dayStartMinute) / $slotSize);

            for ($s = $startSlot; $s < $endSlot && $s < $totalSlots; $s++) {
                if ($s >= 0) {
                    $timeline[$s] = true;
                }
            }
        }

        // Boş slot aralıklarını bul
        $freeSlots = [];
        $slotStart = null;

        for ($s = 0; $s < $totalSlots; $s++) {
            if (!$timeline[$s]) {
                // Boş slot - slot başlat veya devam ettir
                if ($slotStart === null) {
                    $slotStart = $s;
                }
            } else {
                // Dolu slot - slot'u kapat
                if ($slotStart !== null) {
                    $startTime = $dayStartMinute + ($slotStart * $slotSize);
                    $endTime = $dayStartMinute + ($s * $slotSize);

                    $freeSlots[] = [
                        'start' => $this->minutesToTime($startTime),
                        'end' => $this->minutesToTime($endTime)
                    ];
                    $slotStart = null;
                }
            }
        }

        // Son slot'u kapat (eğer gün sonuna kadar boşsa)
        if ($slotStart !== null) {
            $startTime = $dayStartMinute + ($slotStart * $slotSize);
            $freeSlots[] = [
                'start' => $this->minutesToTime($startTime),
                'end' => $dayEnd // Gün sonuna kadar
            ];
        }

        return $freeSlots;
    }
}
