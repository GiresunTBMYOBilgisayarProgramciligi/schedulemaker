<?php
use App\Enums\ExamType;
use App\Enums\ScheduleItemStatus;
use App\Helpers\ScheduleViewHelper;
use App\Models\Schedule;
use function App\Helpers\getSettingValue;

/**
 * @var Schedule $schedule
 */

try {
    $scheduleTypeStr = ExamType::isExamType($schedule->type) ? 'exam' : 'lesson';
    $maxDayIndex     = getSettingValue('maxDayIndex', $scheduleTypeStr, 4);
    $scheduleRows    = ScheduleViewHelper::prepareScheduleRows($schedule, $maxDayIndex);

    if (empty($scheduleRows[0])) {
        echo "<p><em>Program tablosu oluşturulamadı veya henüz ders eklenmemiş.</em></p>";
        return;
    }

    $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
    $slots = $scheduleRows[0]; // Haftalık 1. hafta
    ?>
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-family: Arial, sans-serif;">
        <thead>
            <tr>
                <th style="padding: 8px; border: 1px solid #ddd; background-color: #f2f2f2; text-align: center; font-size: 12px; width: 90px;">Saat</th>
                <?php for ($i = 0; $i <= $maxDayIndex; $i++): ?>
                    <th style="padding: 8px; border: 1px solid #ddd; background-color: #f2f2f2; text-align: center; font-size: 12px;"><?= $days[$i] ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($slots as $slot):
                $slotStart = $slot['slotStartTime']->format('H:i');
                $slotEnd   = $slot['slotEndTime']->format('H:i');

                if ($slotStart === '12:00'):
                    $lunchEnd = (clone $slot['slotEndTime'])->modify('+10 minutes')->format('H:i');
                    $colSpan  = $maxDayIndex + 2;
                    ?>
                    <tr>
                        <td colspan="<?= $colSpan ?>" style="padding: 6px; border: 1px solid #ddd; background-color: #eaf2f8; text-align: center; font-weight: bold; font-size: 11px; color: #2980b9;">
                            <?= $slotStart ?> - <?= $lunchEnd ?> ÖĞLE ARASI
                        </td>
                    </tr>
                    <?php
                    continue;
                endif;

                $timeLabel = "{$slotStart} - {$slotEnd}";
                ?>
                <tr>
                    <td style="padding: 6px; border: 1px solid #ddd; background-color: #fafafa; text-align: center; font-size: 11px; font-weight: bold; white-space: nowrap;"><?= $timeLabel ?></td>
                    <?php for ($i = 0; $i <= $maxDayIndex; $i++):
                        $dayKey = 'day' . $i;
                        $itemOrItems = $slot['days'][$dayKey] ?? null;
                        $cellContent = "";

                        if ($itemOrItems) {
                            $items = is_array($itemOrItems) ? $itemOrItems : [$itemOrItems];
                            $parts = [];
                            foreach ($items as $item) {
                                if (in_array($item->status, [ScheduleItemStatus::PREFERRED->value, ScheduleItemStatus::UNAVAILABLE->value])) {
                                    continue;
                                }
                                $slotDatas = $item->getSlotDatas();
                                foreach ($slotDatas as $sd) {
                                    $lessonName = $sd->lesson ? htmlspecialchars($sd->lesson->getFullName(addGroup: true, addCode: true)) : '';
                                    $classroomName = $sd->classroom ? htmlspecialchars($sd->classroom->name) : '';
                                    $parts[] = "<div style=\"margin-bottom: 3px;\"><strong>{$lessonName}</strong>" . ($classroomName ? "<br><span style=\"color: #7f8c8d; font-size: 10px;\">({$classroomName})</span>" : "") . "</div>";
                                }
                            }
                            $cellContent = implode("<hr style=\"border: none; border-top: 1px dashed #ccc; margin: 3px 0;\">", $parts);
                        }

                        $bgColor = !empty($cellContent) ? '#fdfefe' : '#ffffff';
                        ?>
                        <td style="padding: 6px; border: 1px solid #ddd; background-color: <?= $bgColor ?>; vertical-align: top; font-size: 11px; text-align: center;">
                            <?= $cellContent ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
} catch (Throwable $e) {
    echo "<p><em>Program tablosu yüklenirken bir hata oluştu: " . htmlspecialchars($e->getMessage()) . "</em></p>";
}
