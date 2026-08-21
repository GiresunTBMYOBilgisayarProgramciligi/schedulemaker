<?php

namespace App\Services\Schedule;

use App\Services\BaseService;
use App\Models\Lesson;
use App\Models\ScheduleItem;

/**
 * TimelineService
 * 
 * Schedule item'larını birleştirme (merge) ve parçalama (partial delete) 
 * gibi karmaşık zaman çizelgesi operasyonlarını yönetir.
 */
class TimelineService extends BaseService
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

    /**
     * Verilen schedule item'ın hemen öncesinde ve sonrasında aynı ders/grup verisine sahip
     * bitişik item'lar varsa mevcuttakileri silip tek bir item olarak birleştirir.
     * 
     * @param ScheduleItem $anchor İşlem yapılacak merkez item
     * @param int $break Teneffüs/boşluk süresi (dakika)
     * @return ScheduleItem Birleştirilmiş veya orijinal item
     */
    public function mergeAdjacentItems(ScheduleItem $anchor, int $break = 10): ScheduleItem
    {
        if (!$anchor || !$anchor->id || !$anchor->schedule_id) {
            $this->logger->debug("mergeAdjacentItems atlandı: Geçersiz anchor nesnesi", $this->logContext([
                'anchor_id' => $anchor->id ?? null
            ]));
            return $anchor;
        }

        // Preferred ve Unavailable item'lar birleştirilmez
        if (in_array($anchor->status, ['preferred', 'unavailable'])) {
            $this->logger->debug("mergeAdjacentItems atlandı: Special status", $this->logContext([
                'item_id' => $anchor->id,
                'status' => $anchor->status
            ]));
            return $anchor;
        }

        // Kilitli item'lar birleştirilmez
        if (!empty($anchor->detail['is_locked'])) {
            $this->logger->debug("mergeAdjacentItems atlandı: Item kilitli", $this->logContext([
                'item_id' => $anchor->id
            ]));
            return $anchor;
        }

        // Aynı schedule, gün ve haftaya ait tüm item'ları çek
        $allItems = (new ScheduleItem())->get()->where([
            'schedule_id' => $anchor->schedule_id,
            'day_index'   => $anchor->day_index,
            'week_index'  => $anchor->week_index
        ])->all();

        if (count($allItems) <= 1) {
            $this->logger->debug("mergeAdjacentItems atlandı: Günde tek item var", $this->logContext([
                'schedule_id' => $anchor->schedule_id,
                'day_index' => $anchor->day_index,
                'item_id' => $anchor->id
            ]));
            return $anchor;
        }

        // Başlangıç saatine göre sırala
        usort($allItems, fn($a, $b) => strcmp($a->getShortStartTime(), $b->getShortStartTime()));

        // Anchor item'ın sıralı listedeki indeksini bul
        $anchorIndex = -1;
        foreach ($allItems as $idx => $item) {
            if ((int)$item->id === (int)$anchor->id) {
                $anchorIndex = $idx;
                break;
            }
        }

        if ($anchorIndex === -1) {
            $this->logger->warning("mergeAdjacentItems uyarısı: Anchor sıralı listede bulunamadı", $this->logContext([
                'item_id' => $anchor->id
            ]));
            return $anchor;
        }

        $mergeCandidates = [$anchor];

        // Sola (geriye) doğru tara
        $current = $anchor;
        for ($i = $anchorIndex - 1; $i >= 0; $i--) {
            $prev = $allItems[$i];
            if ($this->areItemsMergeable($current, $prev) && $this->isContiguous($prev, $current, $break)) {
                array_unshift($mergeCandidates, $prev);
                $current = $prev;
            } else {
                break;
            }
        }

        // Sağa (ileri) doğru tara
        $current = $anchor;
        for ($i = $anchorIndex + 1; $i < count($allItems); $i++) {
            $next = $allItems[$i];
            if ($this->areItemsMergeable($current, $next) && $this->isContiguous($current, $next, $break)) {
                $mergeCandidates[] = $next;
                $current = $next;
            } else {
                break;
            }
        }

        // Eğer birleştirilecek başka item yoksa anchor'ı aynen döndür
        if (count($mergeCandidates) <= 1) {
            $this->logger->debug("mergeAdjacentItems: Birleştirilecek komşu öğe bulunamadı", $this->logContext([
                'item_id' => $anchor->id,
                'schedule_id' => $anchor->schedule_id,
                'day_index' => $anchor->day_index
            ]));
            return $anchor;
        }

        // Birleşik zaman aralığını hesapla
        $startTime = $mergeCandidates[0]->getShortStartTime();
        $endTime   = $mergeCandidates[count($mergeCandidates) - 1]->getShortEndTime();
        $candidateIds = array_map(fn($c) => (int)$c->id, $mergeCandidates);
        $oldRanges = array_map(fn($c) => $c->getShortStartTime() . '-' . $c->getShortEndTime(), $mergeCandidates);

        // displaced_preferred verilerini birleştir
        $mergedDisplacedPreferred = [];
        foreach ($mergeCandidates as $cand) {
            if (!empty($cand->detail['displaced_preferred']) && is_array($cand->detail['displaced_preferred'])) {
                foreach ($cand->detail['displaced_preferred'] as $dp) {
                    $mergedDisplacedPreferred[] = $dp;
                }
            }
        }

        $detail = $anchor->detail ?? [];
        if (!empty($mergedDisplacedPreferred)) {
            // Mükerrer verileri temizle
            $uniqueDp = [];
            foreach ($mergedDisplacedPreferred as $dp) {
                $key = ($dp['start'] ?? '') . '-' . ($dp['end'] ?? '');
                $uniqueDp[$key] = $dp;
            }
            $detail['displaced_preferred'] = array_values($uniqueDp);
        }

        $this->logger->debug("Schedule items otomatik birleştiriliyor (auto-merge)", $this->logContext([
            'schedule_id'   => $anchor->schedule_id,
            'day_index'     => $anchor->day_index,
            'status'        => $anchor->status,
            'merged_count'  => count($mergeCandidates),
            'candidate_ids' => $candidateIds,
            'old_ranges'    => $oldRanges,
            'new_range'     => $startTime . '-' . $endTime
        ]));

        // Birleşen tüm elemanları sil
        foreach ($mergeCandidates as $cand) {
            $cand->delete();
        }

        // Yeni birleştirilmiş item'ı oluştur
        $mergedItem = new ScheduleItem();
        $mergedItem->schedule_id = $anchor->schedule_id;
        $mergedItem->day_index   = $anchor->day_index;
        $mergedItem->week_index  = $anchor->week_index;
        $mergedItem->start_time  = $startTime;
        $mergedItem->end_time    = $endTime;
        $mergedItem->status      = $anchor->status;
        $mergedItem->data        = $anchor->data;
        $mergedItem->detail      = !empty($detail) ? $detail : null;
        $mergedItem->create();

        $this->logger->debug("Auto-merge tamamlandı, yeni item oluşturuldu", $this->logContext([
            'new_item_id'  => $mergedItem->id,
            'schedule_id'  => $mergedItem->schedule_id,
            'time_range'   => $startTime . '-' . $endTime
        ]));

        return $mergedItem;
    }

    /**
     * İki schedule item'ın birleştirilebilir olup olmadığını kontrol eder
     * 
     * @param ScheduleItem $item1
     * @param ScheduleItem $item2
     * @return bool
     */
    public function areItemsMergeable(ScheduleItem $item1, ScheduleItem $item2): bool
    {
        if ($item1->status !== $item2->status) {
            $this->logger->debug("Merge engel (Status farkı)", $this->logContext([
                'item1_id' => $item1->id, 'status1' => $item1->status,
                'item2_id' => $item2->id, 'status2' => $item2->status
            ]));
            return false;
        }

        if (in_array($item1->status, ['preferred', 'unavailable'])) {
            return false;
        }

        if (!empty($item1->detail['is_locked']) || !empty($item2->detail['is_locked'])) {
            $this->logger->debug("Merge engel (Öğe kilitli)", $this->logContext([
                'item1_id' => $item1->id, 'item1_locked' => !empty($item1->detail['is_locked']),
                'item2_id' => $item2->id, 'item2_locked' => !empty($item2->detail['is_locked'])
            ]));
            return false;
        }

        // Data karşılaştırması (Normalize edilmiş halleri ile)
        $normData1 = $this->normalizeData($item1->data);
        $normData2 = $this->normalizeData($item2->data);

        if (serialize($normData1) !== serialize($normData2)) {
            $this->logger->debug("Merge engel (Data uyuşmazlığı)", $this->logContext([
                'item1_id' => $item1->id, 'data1' => $normData1,
                'item2_id' => $item2->id, 'data2' => $normData2
            ]));
            return false;
        }

        // Detail karşılaştırması (displaced_preferred ve is_locked hariç)
        $detail1 = $item1->detail ?? [];
        $detail2 = $item2->detail ?? [];
        unset($detail1['displaced_preferred'], $detail1['is_locked']);
        unset($detail2['displaced_preferred'], $detail2['is_locked']);

        if (serialize($detail1) !== serialize($detail2)) {
            $this->logger->debug("Merge engel (Detail uyuşmazlığı)", $this->logContext([
                'item1_id' => $item1->id, 'detail1' => $detail1,
                'item2_id' => $item2->id, 'detail2' => $detail2
            ]));
            return false;
        }

        return true;
    }

    /**
     * ScheduleItem data alanını tutarlı sıralama ile normalize eder
     * 
     * @param mixed $data
     * @return array
     */
    private function normalizeData($data): array
    {
        if (empty($data) || !is_array($data)) {
            return [];
        }

        $normalized = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                ksort($item);
                $normalized[] = $item;
            }
        }

        usort($normalized, function ($a, $b) {
            $l1 = (int)($a['lesson_id'] ?? 0);
            $l2 = (int)($b['lesson_id'] ?? 0);
            if ($l1 !== $l2) {
                return $l1 <=> $l2;
            }
            $u1 = (int)($a['lecturer_id'] ?? 0);
            $u2 = (int)($b['lecturer_id'] ?? 0);
            return $u1 <=> $u2;
        });

        return $normalized;
    }

    /**
     * İki item'ın zaman olarak bitişik olup olmadığını kontrol eder
     * 
     * @param ScheduleItem $prev Öncelikli item
     * @param ScheduleItem $next Sonraki item
     * @param int $breakMinutes İzin verilen maksimum teneffüs/boşluk süresi (dakika)
     * @return bool
     */
    public function isContiguous(ScheduleItem $prev, ScheduleItem $next, int $breakMinutes): bool
    {
        $prevEnd = strtotime($prev->getShortEndTime());
        $nextStart = strtotime($next->getShortStartTime());

        if ($nextStart < $prevEnd) {
            $this->logger->debug("Merge engel (Zaman örtüşmesi veya çakışması)", $this->logContext([
                'prev_id' => $prev->id, 'prev_end' => $prev->getShortEndTime(),
                'next_id' => $next->id, 'next_start' => $next->getShortStartTime()
            ]));
            return false;
        }

        $gapMinutes = ($nextStart - $prevEnd) / 60;
        if ($gapMinutes > $breakMinutes) {
            $this->logger->debug("Merge engel (Teneffüs sınırından fazla saat aralığı)", $this->logContext([
                'prev_id' => $prev->id, 'prev_end' => $prev->getShortEndTime(),
                'next_id' => $next->id, 'next_start' => $next->getShortStartTime(),
                'gap_minutes' => $gapMinutes, 'max_allowed_break' => $breakMinutes
            ]));
            return false;
        }

        return true;
    }
}
