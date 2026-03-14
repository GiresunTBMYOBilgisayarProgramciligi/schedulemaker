<?php

namespace App\Services\Helpers;

use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Helpers\TimeHelper;
use Exception;

/**
 * Schedule item çakışma kontrolü ve çözümleme helper service
 * 
 * ScheduleController'daki conflict resolution mantığını izole eder.
 * Single, group, preferred, unavailable item kurallarını uygular.
 */
class ConflictResolver
{
    /**
     * Tek bir item için tüm owner'larda çakışma kontrolü yapar
     * 
     * @param array $itemData Kontrol edilecek item verisi
     * @param array $owners Owner listesi [['type' => 'user', 'id' => 146], ...]
     * @param Schedule $targetSchedule Item'in ekleneceği schedule
     * @param Lesson $lesson Item'a ait ders
     * @return array Çakışma hataları (boşsa çakışma yok)
     * @throws Exception
     */
    public function checkConflicts(
        array $itemData,
        array $owners,
        Schedule $targetSchedule,
        Lesson $lesson
    ): array {
        $errors = [];
        $dayIndex = $itemData['day_index'];
        $weekIndex = $itemData['week_index'] ?? 0;
        $startTime = $itemData['start_time'];
        $endTime = $itemData['end_time'];

        foreach ($owners as $owner) {
            $ownerType = $owner['type'];
            $ownerId = $owner['id'];
            $lessonContext = $owner['lesson_context'] ?? $lesson;

            if (!$ownerId) {
                continue;
            }

            // İlgili schedule'ları bul
            $scheduleFilters = [
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'semester' => $targetSchedule->semester,
                'academic_year' => $targetSchedule->academic_year,
                'type' => $targetSchedule->type
            ];

            if ($ownerType == 'program') {
                $scheduleFilters['semester_no'] = $owner['semester_no'] ?? $lessonContext->semester_no;
            } else {
                $scheduleFilters['semester_no'] = null;
            }

            $relatedSchedules = (new Schedule())->get()->where($scheduleFilters)->all();

            foreach ($relatedSchedules as $relatedSchedule) {
                // İlgili gün için itemları getir
                $dayItems = (new ScheduleItem())->get()->where([
                    'schedule_id' => $relatedSchedule->id,
                    'day_index' => $dayIndex,
                    'week_index' => $weekIndex
                ])->all();

                foreach ($dayItems as $existingItem) {
                    // Zaman çakışması kontrolü
                    if (TimeHelper::isOverlapping($startTime, $endTime, $existingItem->start_time, $existingItem->end_time)) {
                        $error = $this->resolveConflict($itemData, $existingItem, $lessonContext, $relatedSchedule);
                        if ($error) {
                            $errors[] = $error;
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Zaman çakışması tespit edildiğinde status kurallarına göre hata olup olmadığını belirler
     * 
     * Status: unavailable ve single -> Hata
     * Status: group -> Grup kurallarına uyuyorsa OK, uymuyorsa Hata
     * Status: preferred -> Hata Yok
     * 
     * @param array $newItemData Yeni eklenecek item
     * @param ScheduleItem $existingItem Mevcut çakışan item
     * @param Lesson $newLesson Yeni eklenen ders
     * @param Schedule $currentSchedule Çakışmanın yaşandığı schedule
     * @return string|null Hata mesajı veya null (çakışma yok)
     */
    public function resolveConflict(
        array $newItemData,
        ScheduleItem $existingItem,
        Lesson $newLesson,
        Schedule $currentSchedule
    ): ?string {
        // Kendi kendisiyle çakışıyorsa (update durumu) yoksay
        if (isset($newItemData['id']) && $newItemData['id'] == $existingItem->id) {
            return null;
        }

        $crashInfo = "{$currentSchedule->getScheduleScreenName()} ({$existingItem->start_time} - {$existingItem->end_time})";

        // Status kontrolü
        switch ($existingItem->status) {
            case 'unavailable':
                return "{$crashInfo}: Bu saat aralığı uygun değil.";

            case 'single':
                // Single ders varsa üzerine ders eklenemez
                $lessonName = $this->getLessonNameFromItem($existingItem);
                return "{$crashInfo}: Bu saatte zaten bir ders mevcut: " . $lessonName;

            case 'group':
                // Grup mantığı
                return $this->checkGroupConflict($newItemData, $existingItem, $newLesson, $crashInfo);

            case 'preferred':
                // Tercih edilen saat, çakışma yok
                return null;

            default:
                return "{$crashInfo}: Bilinmeyen durum: " . $existingItem->status;
        }
    }

    /**
     * Group item çakışma kurallarını kontrol eder
     * 
     * Kurallar:
     * - Yeni ders de grup dersi olmalı
     * - Dersler farklı olmalı
     * - Hoca aynı olmamalı
     * - Grup numaraları farklı olmalı
     * 
     * @param array $newItemData Yeni item verisi
     * @param ScheduleItem $existingItem Mevcut group item
     * @param Lesson $newLesson Yeni ders
     * @param string $crashInfo Hata mesajı prefix'i
     * @return string|null Hata mesajı veya null
     */
    private function checkGroupConflict(
        array $newItemData,
        ScheduleItem $existingItem,
        Lesson $newLesson,
        string $crashInfo
    ): ?string {
        // Yeni ders aynı zamanda grup dersi olmalı
        if ($newLesson->group_no < 1) {
            return "{$crashInfo}: Grup dersi üzerine normal ders eklenemez.";
        }

        // Mevcut gruptaki dersleri kontrol et
        $slotDatas = $existingItem->getSlotDatas();
        foreach ($slotDatas as $sd) {
            if (!$sd->lesson) {
                continue;
            }

            // Dersler farklı olmalı
            // Not: Eğer birleştirilmiş ders ise, parent dersleri de kontrol etmeliyiz
            $newLessonId = (int) $newLesson->id;
            $existingLessonId = (int) $sd->lesson->id;
            $newParentId = $newLesson->parent_lesson_id ? (int) $newLesson->parent_lesson_id : $newLessonId;
            $existingParentId = $sd->lesson->parent_lesson_id ? (int) $sd->lesson->parent_lesson_id : $existingLessonId;

            if ($newLessonId == $existingLessonId || $newParentId == $existingParentId) {
                return "{$crashInfo}: Aynı ders (veya birleşmiş hali) aynı saatte tekrar eklenemez (Grup olsa bile).";
            }

            // Hoca aynı olmamalı
            $newLecturerId = $newItemData['data'][0]['lecturer_id'] ?? null;
            if ($sd->lecturer && $newLecturerId && $sd->lecturer->id == $newLecturerId) {
                return "{$crashInfo}: Hoca aynı anda iki farklı derse giremez: " . $sd->lecturer->getFullName();
            }

            // Grup numaraları farklı olmalı
            if ($sd->lesson->group_no == $newLesson->group_no) {
                return "{$crashInfo}: Aynı grup numarasına sahip dersler çakışamaz.";
            }
        }

        // Buraya geldiyse uygun (Farklı ders, farklı grup, ikisi de grup)
        return null;
    }


    /**
     * Schedule item'dan ders ismini alır
     * 
     * @param ScheduleItem $item
     * @return string Ders ismi
     */
    private function getLessonNameFromItem(ScheduleItem $item): string
    {
        $slotDatas = $item->getSlotDatas();
        if (!empty($slotDatas) && isset($slotDatas[0]->lesson)) {
            return $slotDatas[0]->lesson->name;
        }
        return 'Bilinmeyen Ders';
    }
}
