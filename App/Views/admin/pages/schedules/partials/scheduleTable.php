<?php

use App\Core\Log;

/**
 * @var array $scheduleRows
 */
?>
<div class="schedule-table-container">
    <table class="schedule-table">
        <thead>
            <tr>
                <th class="time-slot">Saat</th>
                <?php foreach ($dayHeaders as $dayHeader): ?>
                    <?= $dayHeader ?>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($scheduleRows as $scheduleRow): ?>
                <?php if ($scheduleRow['slotStartTime']->format('H:i') === '12:00'): ?>
                    <!-- Öğle Arası -->
                    <tr style="background-color: #fcfcfc;">
                        <td class="time-slot"><?= $scheduleRow['slotStartTime']->format('H:i') ?> -
                            <?= $scheduleRow['slotEndTime']->modify('+10 minutes')->format('H:i') ?>
                        </td>
                        <td colspan="<?= count($dayHeaders) ?>"
                            style="text-align: center; color: #888; font-weight: bold; letter-spacing: 2px;">ÖĞLE
                            ARASI
                        </td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td class="time-slot"><?= $scheduleRow['slotStartTime']->format('H:i') ?> -
                            <?= $scheduleRow['slotEndTime']->format('H:i') ?>
                        </td>

                        <?php foreach ($scheduleRow['days'] as $scheduleItem): ?>
                            <?php if ($scheduleItem): ?>
                                <td class="drop-zone">
                                    <?php if ($scheduleItem->status === 'group'): ?>
                                        <div class="lesson-group-container">
                                        <?php endif; ?>
                                        <?php if (count($scheduleItem->getSlotDatas()) > 0): ?>
                                            <?php foreach ($scheduleItem->getSlotDatas() as $slotData): ?>
                                                <div class="lesson-card <?= $slotData->lesson->getScheduleCSSClass() ?>">
                                                    <span class="lesson-name"><?= $slotData->lesson->name ?></span>
                                                    <div class="lesson-meta">
                                                        <span class="lesson-lecturer"><i class="fas fa-user-tie"></i>
                                                            <?= $slotData->lecturer->getFullName() ?></span>
                                                        <span class="lesson-classroom"><i class="fas fa-door-open"></i>
                                                            <?= $slotData->classroom->name ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach ?>
                                        <?php else: ?>
                                            <div class="empty-slot <?= $scheduleItem->getSlotCSSClass() ?>">
                                                <?php if ($scheduleItem->description): ?>
                                                    <div class="note-icon" data-bs-toggle="popover" data-bs-placement="left"
                                                        data-bs-trigger="hover" data-bs-content="<?= $scheduleItem->description ?>"
                                                        data-bs-original-title="Açıklama">
                                                        <i class="bi bi-chat-square-text-fill"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($scheduleItem->status === 'group'): ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <td class="drop-zone">
                                    <div class="empty-slot"></div>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>