<?php
use App\Models\Schedule;
use function App\Helpers\getSettingValue;

/**
 * @var array $scheduleRows
 * @var Schedule $schedule
 */
?>
<div class="schedule-table-container">
    <table class="schedule-table active" data-week-index="0"><!-- todo week index düzenlemesi yapılacak -->
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
                data-semester="Güz"
                data-lesson-hours="2"
                >
                -->
                        <?php foreach ($scheduleRow['days'] as $scheduleItem): ?>
                            <?php if ($scheduleItem):
                                //Log::logger()->debug('scheduleItem', ['scheduleItem' => $scheduleItem]);
                                $dropZone = ($scheduleItem->status === 'unavailable' || (isset($only_table) && $only_table)) ? '' : 'drop-zone'; ?>
                                <td class="<?= $dropZone ?>" data-start-time="<?= $scheduleRow['slotStartTime']->format('H:i') ?>"
                                    data-end-time="<?= $scheduleRow['slotEndTime']->format('H:i') ?>"
                                    data-schedule-item-id="<?= $scheduleItem->id ?>">
                                    <?php if ($scheduleItem->status === 'group'): ?>
                                        <div class="lesson-group-container">
                                    <?php endif; ?>
                                        <?php if (count($scheduleItem->getSlotDatas()) > 0): ?>
                                            <?php foreach ($scheduleItem->getSlotDatas() as $slotData):
                                                $draggable = "true";
                                                if (!is_null($slotData->lesson->parent_lesson_id) or $schedule->academic_year != getSettingValue('academic_year') or $schedule->semester != getSettingValue('semester') or (isset($only_table) && $only_table) or (isset($preference_mode) && $preference_mode)) {
                                                    $draggable = "false";
                                                }
                                                ?>
                                                <div class="lesson-card <?= $slotData->lesson->getScheduleCSSClass() ?>"
                                                    draggable="<?= $draggable ?>" data-schedule-item-id="<?= $scheduleItem->id ?>"
                                                    data-group-no="<?= $slotData->lesson->group_no ?>"
                                                    data-lesson-id="<?= $slotData->lesson->id ?>"
                                                    data-lesson-code="<?= $slotData->lesson->code ?>" data-size="<?= $slotData->lesson->size ?>"
                                                    data-lecturer-id="<?= $slotData->lecturer->id ?>"
                                                    data-classroom-id="<?= $slotData->classroom->id ?>"
                                                    data-classroom-size="<?= $slotData->classroom->class_size ?>"
                                                    data-classroom-exam-size="<?= $slotData->classroom->exam_size ?>"
                                                    data-status="<?= $scheduleItem->status ?>">
                                                    <?php if ((!isset($only_table) or !$only_table) && (!isset($preference_mode) or !$preference_mode)): ?>
                                                        <input type="checkbox" class="lesson-bulk-checkbox" title="Toplu işlem için seç">
                                                    <?php endif; ?>
                                                    <span class="lesson-name">
                                                        <a class='text-decoration-none' target='_blank' style="color: inherit;"
                                                            href='/admin/lesson/<?= $slotData->lesson->id ?>'>
                                                            <?= $slotData->lesson->name ?>
                                                        </a>
                                                    </span>
                                                    <div class="lesson-meta">
                                                        <span class="lesson-lecturer">
                                                            <a class='text-decoration-none' target='_blank' style="color: inherit;"
                                                                href='/admin/profile/<?= $slotData->lecturer->id ?>'>
                                                                <?= $slotData->lecturer->getFullName() ?></a>
                                                        </span>
                                                        <span class="lesson-classroom">
                                                            <a class='text-decoration-none' target='_blank' style="color: inherit;"
                                                                href='/admin/classroom/<?= $slotData->classroom->id ?>'>
                                                                <?= $slotData->classroom->name ?></a>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach ?>
                                        <?php else: ?>
                                            <div class="empty-slot dummy <?= $scheduleItem->getSlotCSSClass() ?>"
                                                draggable="<?= (isset($preference_mode) && $preference_mode) ? 'true' : 'false' ?>"
                                                data-schedule-item-id="<?= $scheduleItem->id ?>" data-status="<?= $scheduleItem->status ?>"
                                                data-detail='<?= json_encode($scheduleItem->detail) ?>'>
                                                <?php if (isset($preference_mode) && $preference_mode): ?>
                                                    <input type="checkbox" class="lesson-bulk-checkbox" title="Toplu işlem için seç">
                                                <?php endif; ?>
                                                <?php if (is_array($scheduleItem->detail) && array_key_exists('description', $scheduleItem->detail)): ?>
                                                    <div class="note-icon" data-bs-toggle="popover" data-bs-placement="left"
                                                        data-bs-trigger="hover" data-bs-content="<?= $scheduleItem->detail['description'] ?>"
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
                                <td class="drop-zone" data-start-time="<?= $scheduleRow['slotStartTime']->format('H:i') ?>"
                                    data-end-time="<?= $scheduleRow['slotEndTime']->format('H:i') ?>">
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