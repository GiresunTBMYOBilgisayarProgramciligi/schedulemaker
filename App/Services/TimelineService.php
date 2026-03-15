<?php

namespace App\Services;

use App\Helpers\TimeHelper;
use App\Models\Lesson;
use App\Models\ScheduleItem;

/**
 * TimelineService
 * 
 * Schedule item'larını birleştirme (merge) ve parçalama (partial delete) 
 * gibi karmaşık zaman çizelgesi operasyonlarını yönetir.
 */
class TimelineService
{
    /**
     * "Flatten Timeline" mantığı ile zaman çizelgesini kritik noktalara ayırır.
     * 
     * @param string $start Başlangıç (HH:MM)
     * @param string $end Bitiş (HH:MM)
     * @param array $internalPoints İç sınır noktaları
     * @param int $duration Standart ders süresi
     * @param int $break Standart teneffüs süresi
     * @return array Sıralı kritik zaman noktaları
     */
    public function getCriticalPoints(string $start, string $end, array $internalPoints, int $duration, int $break): array
    {
        $points = [$start, $end];

        // Slot sınırlarını ekle
        $current = strtotime($start);
        $endUnix = strtotime($end);

        while ($current < $endUnix) {
            $current += ($duration * 60);
            if ($current <= $endUnix) {
                $points[] = date("H:i", $current);
                if ($current < $endUnix) {
                    $current += ($break * 60);
                    $points[] = date("H:i", $current);
                }
            }
        }

        // Ekstra noktaları ekle
        foreach ($internalPoints as $p) {
            $p = substr($p, 0, 5);
            if ($p > $start && $p < $end) {
                $points[] = $p;
            }
        }

        $points = array_unique($points);
        sort($points);
        return $points;
    }

    /**
     * Belirli dilimler (segments) üzerinde temizlik yaparak bitişik ve aynı veriye sahip dilimleri birleştirir
     * 
     * @param array $segments Dilim listesi
     * @param int $break Teneffüs süresi
     * @return array Birleştirilmiş dilimler
     */
    public function mergeContiguousSegments(array $segments, int $break): array
    {
        // 1. Teneffüs Temizliği (Break Sanitization)
        for ($i = 0; $i < count($segments); $i++) {
            if ($segments[$i]['isBreak']) {
                $prevKept = ($i > 0 && $segments[$i - 1]['shouldKeep']);
                $nextKept = ($i < count($segments) - 1 && $segments[$i + 1]['shouldKeep']);

                // Bir teneffüs ancak her iki tarafında da aynı ders varsa tutulur
                $isDataSame = ($prevKept && $nextKept && 
                    serialize($segments[$i - 1]['data']) === serialize($segments[$i + 1]['data']) &&
                    serialize($segments[$i - 1]['detail'] ?? []) === serialize($segments[$i + 1]['detail'] ?? [])
                );

                if (!$isDataSame) {
                    $segments[$i]['shouldKeep'] = false;
                    $segments[$i]['data'] = [];
                    $segments[$i]['detail'] = [];
                } else {
                    $segments[$i]['data'] = $segments[$i - 1]['data'];
                    $segments[$i]['detail'] = $segments[$i - 1]['detail'] ?? [];
                }
            }
        }

        // 2. Birleştirme
        $merged = [];
        foreach ($segments as $seg) {
            if (!$seg['shouldKeep']) {
                continue;
            }

            $lastIdx = count($merged) - 1;
            if (
                $lastIdx >= 0 &&
                $merged[$lastIdx]['end'] === $seg['start'] &&
                serialize($merged[$lastIdx]['data']) === serialize($seg['data']) &&
                serialize($merged[$lastIdx]['detail'] ?? []) === serialize($seg['detail'] ?? [])
            ) {
                $merged[$lastIdx]['end'] = $seg['end'];
            } else {
                $merged[] = [
                    'start' => $seg['start'],
                    'end' => $seg['end'],
                    'data' => $seg['data'],
                    'detail' => $seg['detail'] ?? []
                ];
            }
        }

        return $merged;
    }

    /**
     * Dilimler için doğru status belirlemesini yapar
     * 
     * @param array $data Dilim verisi
     * @param string $originalStatus Orijinal status
     * @param bool $wasPreferred Orijinal alanın preferred olup olmadığı
     * @return string Yeni status
     */
    public function determineStatus(array $data, string $originalStatus, bool $wasPreferred = false, array $lessonGroups = []): string
    {
        if (in_array($originalStatus, ['preferred', 'unavailable'])) {
            return $originalStatus;
        }

        if (empty($data)) {
            return $wasPreferred ? 'preferred' : 'single';
        }

        $isGroup = false;
        foreach ($data as $d) {
            $lessonId = $d['lesson_id'] ?? null;
            if ($lessonId) {
                // Eğer lessonGroups parametresi ile grup bilgileri geldiyse (testler için)
                if (isset($lessonGroups[$lessonId])) {
                    if ($lessonGroups[$lessonId] > 0) {
                        $isGroup = true;
                        break;
                    }
                    continue;
                }

                // Normal akış: DB'den çek
                $lesson = (new Lesson())->find($lessonId);
                if ($lesson && $lesson->group_no > 0) {
                    $isGroup = true;
                    break;
                }
            }
        }

        return $isGroup ? 'group' : 'single';
    }
}
