<?php

use function App\Helpers\e;

/**
 * Boş Dummy slot partial'ı Tercih edilen yada uygun olmayan derslerde kullanılır.
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
    data-schedule-item-id="<?= (int)$scheduleItem->id ?>" data-status="<?= e((string)$scheduleItem->status) ?>"
    data-detail='<?= json_encode($scheduleItem->detail, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
    <?php if (isset($preference_mode) && $preference_mode): ?>
        <input type="checkbox" class="lesson-bulk-checkbox" title="Toplu işlem için seç">
    <?php endif; ?>
    <?php if (is_array($scheduleItem->detail) && array_key_exists('description', $scheduleItem->detail)): ?>
        <div class="note-icon" data-bs-toggle="popover" data-bs-placement="left"
            data-bs-trigger="hover"
            data-bs-content="<?= e($scheduleItem->detail['description']) ?>"
            data-bs-original-title="Açıklama">
            <i class="bi bi-chat-square-text-fill"></i>
        </div>
    <?php endif; ?>
</div>
