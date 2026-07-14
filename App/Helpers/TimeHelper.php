<?php

namespace App\Helpers;

use function App\Helpers\getSettingValue;

/**
 * TimeHelper
 * 
 * Program ve sınav saatleri ile ilgili zaman hesaplama yardımcı metotları
 */
class TimeHelper
{
    /**
     * İki zaman arasındaki süreyi dakika cinsinden hesaplar
     * 
     * @param string $startTime HH:MM formatı
     * @param string $endTime HH:MM formatı
     * @return int Dakika cinsinden süre
     */
    public static function getDurationMinutes(string $startTime, string $endTime): int
    {
        $start = \DateTime::createFromFormat('H:i', $startTime);
        $end = \DateTime::createFromFormat('H:i', $endTime);

        if (!$start || !$end) {
            return 0;
        }

        $duration = ($end->getTimestamp() - $start->getTimestamp()) / 60;
        return max(0, (int)$duration);
    }

    /**
     * Başlangıç saatinden itibaren belirli bir saat (float) ekleyerek bitiş saatini hesaplar
     * 
     * @param string $startTime HH:MM formatı
     * @param float $hours Eklenecek saat (örn: 1.5)
     * @return string HH:MM formatında bitiş saati
     */
    public static function calculateEndTimeByHours(string $startTime, float $hours): string
    {
        $start = new \DateTime($startTime);
        $minutes = (int) ($hours * 60);
        $end = clone $start;
        $end->modify("+{$minutes} minutes");
        return $end->format('H:i');
    }

    /**
     * Slot bazlı yeni bir bitiş saati hesaplar
     * 
     * @param string $startTime Başlangıç saati (HH:MM)
     * @param int $slots Slot sayısı
     * @param int $slotSizeMinutes Slot boyutu (dakika)
     * @param string $type Tip ('lesson' veya 'exam')
     * @param int|null $customDuration Manuel süre (dakika) - Testler için
     * @return string HH:MM formatında yeni bitiş saati
     */
    public static function calculateEndTimeBySlots(string $startTime, int $slots, int $slotSizeMinutes, string $type = 'lesson', ?int $customDuration = null): string
    {
        $start = strtotime($startTime);
        
        $duration = $customDuration ?? (int) getSettingValue('duration', $type, $type === 'exam' ? 30 : 50);

        // Slot sayısı kadar ilerle - son slot'ta ara/teneffüs (break) yok
        $totalMinutes = ($slots - 1) * $slotSizeMinutes + $duration;
        $end = $start + ($totalMinutes * 60);

        return date('H:i', $end);
    }

    /**
     * Bir öğenin (item) kaç slot kapladığını hesaplar
     * 
     * @param string $startTime Başlangıç saati
     * @param string $endTime Bitiş saati
     * @param int $slotSizeMinutes Slot boyutu (dakika)
     * @return int Slot sayısı
     */
    public static function calculateItemSlots(string $startTime, string $endTime, int $slotSizeMinutes): int
    {
        $totalMinutes = self::getDurationMinutes($startTime, $endTime);
        return (int) ceil($totalMinutes / $slotSizeMinutes);
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
    public static function isOverlapping(string $start1, string $end1, string $start2, string $end2): bool
    {
        $s1 = strtotime($start1);
        $e1 = strtotime($end1);
        $s2 = strtotime($start2);
        $e2 = strtotime($end2);

        return !($e1 <= $s2 || $s1 >= $e2);
    }

    /**
     * İki zaman aralığının kesişim (overlap) bölgesini döndürür.
     *
     * Her iki aralık da HH:MM ya da HH:MM:SS formatında olabilir; karşılaştırma
     * string sıralamasıyla yapılır (aynı formatta oldukları varsayılır).
     * Kesişim yoksa (bitişik veya ayrık aralıklar dahil) null döner.
     *
     * Örnek:
     *   getOverlapInterval('08:00', '11:50', '09:00', '13:00')
     *   => ['start' => '09:00', 'end' => '11:50']
     *
     *   getOverlapInterval('08:00', '09:00', '09:00', '10:00')
     *   => null  (sadece bitişik, gerçek örtüşme yok)
     *
     * @param string $start1 Birinci aralık başlangıcı (HH:MM veya HH:MM:SS)
     * @param string $end1   Birinci aralık bitişi   (HH:MM veya HH:MM:SS)
     * @param string $start2 İkinci aralık başlangıcı (HH:MM veya HH:MM:SS)
     * @param string $end2   İkinci aralık bitişi    (HH:MM veya HH:MM:SS)
     * @return array{start: string, end: string}|null Kesişim aralığı ya da null
     */
    public static function getOverlapInterval(
        string $start1,
        string $end1,
        string $start2,
        string $end2
    ): ?array {
        // H:i:s formatındaki değerleri H:i'ye normalize et (karşılaştırma tutarlılığı)
        $s1 = substr($start1, 0, 5);
        $e1 = substr($end1,   0, 5);
        $s2 = substr($start2, 0, 5);
        $e2 = substr($end2,   0, 5);

        $overlapStart = max($s1, $s2);
        $overlapEnd   = min($e1, $e2);

        // Bitişik (touching) veya ayrık aralıklar → kesişim yok
        if ($overlapStart >= $overlapEnd) {
            return null;
        }

        return ['start' => $overlapStart, 'end' => $overlapEnd];
    }
}
