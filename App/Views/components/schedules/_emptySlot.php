<?php

/**
 * Boş slot partial'ı
 *
 * Hem ders hem sınav programı tablolarında ortak kullanılır.
 * İçi boş olan ve status'e göre CSS sınıfı alan slotları render eder.
 *
 * Beklenen değişkenler:
 * @var \App\Models\ScheduleItem $scheduleItem  Schedule item nesnesi
 * @var bool $preference_mode  Tercih modu mu
 */
?>
<div class="empty-slot dummy <?= $scheduleItem->getSlotCSSClass() ?>"
    draggable="<?= (isset($preference_mode) && $preference_mode) ? 'true' : 'false' ?>"
    data-schedule-item-id="<?= $scheduleItem->id ?>" data-status="<?= $scheduleItem->status ?>"
    data-detail='<?= json_encode($scheduleItem->detail) ?>'>
    <?php if (isset($preference_mode) && $preference_mode): ?>
        <input type="checkbox" class="lesson-bulk-checkbox" title="Toplu işlem için seç">
    <?php endif; ?>
    <?php if (is_array($scheduleItem->detail) && array_key_exists('description', $scheduleItem->detail)): ?>
        <div class="note-icon" data-bs-toggle="popover" data-bs-placement="left"
            data-bs-trigger="hover"
            data-bs-content="<?= htmlspecialchars($scheduleItem->detail['description']) ?>"
            data-bs-original-title="Açıklama">
            <i class="bi bi-chat-square-text-fill"></i>
        </div>
    <?php endif; ?>
</div>
