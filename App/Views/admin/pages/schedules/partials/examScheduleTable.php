<?php
use App\Models\Schedule;
use App\Helpers\ScheduleViewHelper;
use function App\Helpers\getSettingValue;

/**
 * @var array $weekRows
 * @var array $weekHeaders
 * @var Schedule $schedule
 * @var bool $only_table
 * @var bool $preference_mode
 */

// rowspan takibi için
$coveredCells = []; // [$weekIndex][$rowIndex][$dayIndex]
?>
<div class="schedule-table-container">
    <?php
    foreach ($weekRows as $weekIndex => $scheduleRows):
        $dayHeaders = $weekHeaders[$weekIndex] ?? [];
        $displayClass = ($weekIndex === 0) ? 'active' : 'd-none';
        $totalRows = count($scheduleRows);
        ?>
        <table class="schedule-table <?= $displayClass ?>" data-week-index="<?= $weekIndex ?>"
               role="grid" aria-label="Sınav Programı - Hafta <?= $weekIndex + 1 ?>">
            <thead>
                <tr>
                    <th class="time-slot">Saat</th>
                    <?php foreach ($dayHeaders as $dayHeader): ?>
                        <?= $dayHeader ?>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scheduleRows as $rowIndex => $scheduleRow): ?>
                    <tr>
                        <td class="time-slot">
                            <?= $scheduleRow['slotStartTime']->format('H:i') ?> -
                            <?= $scheduleRow['slotEndTime']->format('H:i') ?>
                        </td>
                        <?php foreach ($scheduleRow['days'] as $dayIndex => $scheduleItem): ?>
                            <?php
                            if (isset($coveredCells[$weekIndex][$rowIndex][$dayIndex])) {
                                continue;
                            }
                            ?>

                            <?php if ($scheduleItem):
                                // Rowspan hesapla
                                $rowSpan = 1;
                                $itemId = $scheduleItem->id;
                                for ($i = $rowIndex + 1; $i < $totalRows; $i++) {
                                    $nextItem = $scheduleRows[$i]['days'][$dayIndex] ?? null;
                                    if ($nextItem && $nextItem->id === $itemId) {
                                        $rowSpan++;
                                        $coveredCells[$weekIndex][$i][$dayIndex] = true;
                                    } else {
                                        break;
                                    }
                                }

                                $dropZone = ($scheduleItem->status === 'unavailable' || (isset($only_table) && $only_table)) ? '' : 'drop-zone'; ?>
                                <td class="<?= $dropZone ?>" rowspan="<?= $rowSpan ?>"
                                    data-start-time="<?= $scheduleRow['slotStartTime']->format('H:i') ?>"
                                    data-end-time="<?= $scheduleRows[$rowIndex + $rowSpan - 1]['slotEndTime']->format('H:i') ?>"
                                    data-day-index="<?= (int)filter_var($dayIndex, FILTER_SANITIZE_NUMBER_INT) ?>" data-schedule-item-id="<?= $scheduleItem->id ?>">

                                    <?php if ($scheduleItem->status === 'group'): ?>
                                        <div class="lesson-group-container h-100">
                                    <?php endif; ?>

                                    <?php if (count($scheduleItem->getSlotDatas()) > 0): ?>
                                        <?php foreach ($scheduleItem->getSlotDatas() as $slotData):
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
                                                'type' => 'exam',
                                                'only_table' => $only_table ?? false,
                                                'preference_mode' => $preference_mode ?? false
                                            ]);
                                        endforeach; ?>
                                    <?php else: ?>
                                        <?= \App\Core\View::renderComponent('schedules/_emptySlot', [
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
                                    data-end-time="<?= $scheduleRow['slotEndTime']->format('H:i') ?>" data-day-index="<?= (int)filter_var($dayIndex, FILTER_SANITIZE_NUMBER_INT) ?>">
                                    <div class="empty-slot"></div>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</div>