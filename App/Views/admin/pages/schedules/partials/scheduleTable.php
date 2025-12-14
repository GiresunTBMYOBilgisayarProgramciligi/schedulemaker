<?php

use App\Core\Log;
use App\Models\Schedule;
use function App\Helpers\getSettingValue;

/**
 * @var array $scheduleRows
 * @var Schedule $schedule
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
                <!-- 
                <div 
                id="scheduleTable-lesson-755-1" 
                data-lesson-code="BILP-113"
                data-semester-no="1" 
                data-lesson-id="755"
                data-lecturer-id="154"
                data-time="10.00 - 10.50"
                data-day-index="0"
                data-semester="Güz"
                data-academic-year="2025 - 2026"
                data-classroom-id="7"
                data-lesson-hours="2"
                data-size="0"
                data-classroom-exam-size="35"
                data-classroom-size="0"
                >
        
                -->
                        <?php foreach ($scheduleRow['days'] as $scheduleItem): ?>
                            <?php if ($scheduleItem): 
                                Log::logger()->debug('scheduleItem', ['scheduleItem' => $scheduleItem]);?>
                                <td class="drop-zone" data-start-time="<?= $scheduleRow['slotStartTime']->format('H:i') ?>" data-end-time="<?= $scheduleRow['slotEndTime']->format('H:i') ?>">
                                    <?php if ($scheduleItem->status === 'group'): ?>
                                        <div class="lesson-group-container">
                                        <?php endif; ?>
                                        <?php if (count($scheduleItem->getSlotDatas()) > 0): ?>
                                            <?php foreach ($scheduleItem->getSlotDatas() as $slotData):
                                                $draggable = "true";
                                                if (!is_null($slotData->lesson->parent_lesson_id) or $schedule->academic_year != getSettingValue('academic_year') or $schedule->semester != getSettingValue('semester')) {
                                                    $draggable = "false";
                                                }
                                                ?>
                                                <div class="lesson-card <?= $slotData->lesson->getScheduleCSSClass() ?>" 
                                                draggable="<?= $draggable ?>" 
                                                data-schedule-item-id="<?= $scheduleItem->id ?>" 
                                                data-group-no="<?= $slotData->lesson->group_no ?>"
                                                data-lesson-id="<?= $slotData->lesson->id ?>"
                                                data-lesson-code="<?= $slotData->lesson->code ?>"
                                                data-lecturer-id="<?= $slotData->lecturer->id ?>"
                                                >
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
                                <td class="drop-zone" data-start-time="<?= $scheduleRow['slotStartTime']->format('H:i') ?>" data-end-time="<?= $scheduleRow['slotEndTime']->format('H:i') ?>">
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