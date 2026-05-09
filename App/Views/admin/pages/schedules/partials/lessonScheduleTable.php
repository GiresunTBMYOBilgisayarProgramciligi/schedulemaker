<?php
use App\Models\Schedule;
use App\Helpers\ScheduleViewHelper;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSettingValue;

/**
 * @var array $weekRows
 * @var array $weekHeaders
 * @var Schedule $schedule
 * @var bool $only_table
 * @var bool $preference_mode
 */
?>
<div class="schedule-table-container">
    <?php
    foreach ($weekRows as $weekIndex => $scheduleRows):
        $dayHeaders = $weekHeaders[$weekIndex] ?? [];
        $displayClass = ($weekIndex === 0) ? 'active' : 'd-none';
        ?>
        <table class="schedule-table <?= $displayClass ?>" data-week-index="<?= $weekIndex ?>" role="grid"
            aria-label="Ders Programı">
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
                        <tr class="lunch-break-row">
                            <td class="time-slot">
                                <?= $scheduleRow['slotStartTime']->format('H:i') ?> -
                                <?= $scheduleRow['slotEndTime']->modify('+10 minutes')->format('H:i') ?>
                            </td>
                            <td class="lunch-break-cell" colspan="<?= count($dayHeaders) ?>">ÖĞLE ARASI</td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td class="time-slot">
                                <?= $scheduleRow['slotStartTime']->format('H:i') ?> -
                                <?= $scheduleRow['slotEndTime']->format('H:i') ?>
                            </td>
                            <?php foreach ($scheduleRow['days'] as $dayIndex => $scheduleItem): ?>
                                <?php if ($scheduleItem):
                                    $dropZone = ($scheduleItem->status === 'unavailable' || (isset($only_table) && $only_table)) ? '' : 'drop-zone'; ?>
                                    <td class="<?= $dropZone ?>" data-start-time="<?= $scheduleRow['slotStartTime']->format('H:i') ?>"
                                        data-end-time="<?= $scheduleRow['slotEndTime']->format('H:i') ?>"
                                        data-schedule-item-id="<?= $scheduleItem->id ?>"
                                        data-day-index="<?= (int) filter_var($dayIndex, FILTER_SANITIZE_NUMBER_INT) ?>">
                                        <?php if ($scheduleItem->status === 'group'): ?>
                                            <div class="lesson-group-container">
                                            <?php endif; ?>

                                            <?php if (count($scheduleItem->getSlotDatas()) > 0): ?>
                                                <?php foreach ($scheduleItem->getSlotDatas() as $slotData):
                                                    $isChild = !is_null($slotData->lesson->parent_lesson_id);
                                                    $draggable = ScheduleViewHelper::isDraggable(
                                                        $slotData,
                                                        $schedule,
                                                        isset($only_table) && $only_table,
                                                        isset($preference_mode) && $preference_mode
                                                    );
                                                    echo \App\Core\View::renderComponent('schedules/_lessonCard', [
                                                        'scheduleItem' => $scheduleItem,
                                                        'slotData' => $slotData,
                                                        'schedule' => $schedule,
                                                        'draggable' => $draggable,
                                                        'type' => 'lesson',
                                                        'only_table' => $only_table ?? false,
                                                        'preference_mode' => $preference_mode ?? false
                                                    ]);
                                                endforeach; ?>
                                            <?php else: ?>
                                                <?= \App\Core\View::renderComponent('schedules/_emptySlotDummy', [
                                                    'scheduleItem' => $scheduleItem,
                                                    'preference_mode' => $preference_mode ?? false
                                                ]) ?>
                                            <?php endif; ?>

                                            <?php if ($scheduleItem->status === 'group'): ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php else: ?>
                                    <td class="drop-zone" data-start-time="<?= $scheduleRow['slotStartTime']->format('H:i') ?>"
                                        data-end-time="<?= $scheduleRow['slotEndTime']->format('H:i') ?>"
                                        data-day-index="<?= (int) filter_var($dayIndex, FILTER_SANITIZE_NUMBER_INT) ?>">
                                        <div class="empty-slot"></div>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</div>