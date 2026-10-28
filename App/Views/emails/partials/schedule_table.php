<?php
use App\Enums\ExamType;
use App\Helpers\ScheduleViewHelper;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use function App\Helpers\getSettingValue;

/**
 * @var Schedule $schedule
 */

/**
 * Hücredeki kayıtların ders ve derslik bilgisini e-posta için HTML olarak döndürür.
 *
 * @param ScheduleItem[] $items
 */
$renderCellContent = function (array $items): string {
    $parts = [];
    foreach ($items as $item) {
        foreach ($item->getSlotDatas() as $sd) {
            $lessonName = $sd->lesson ? htmlspecialchars($sd->lesson->getFullName(addGroup: true, addCode: true)) : '';
            $classroomName = $sd->classroom ? htmlspecialchars($sd->classroom->name) : '';
            $parts[] = "<div style=\"margin-bottom: 3px;\"><strong>{$lessonName}</strong>" . ($classroomName ? "<br><span style=\"color: #7f8c8d; font-size: 10px;\">({$classroomName})</span>" : "") . "</div>";
        }
    }
    return implode("<hr style=\"border: none; border-top: 1px dashed #ccc; margin: 3px 0;\">", $parts);
};

try {
    $isExam          = ExamType::isExamType($schedule->type);
    $maxDayIndex     = getSettingValue('maxDayIndex', $isExam ? 'exam' : 'lesson', 4);
    // Tercih / uygun değil kayıtları e-postada gösterilmez
    $scheduleRows    = ScheduleViewHelper::stripDummyItems(ScheduleViewHelper::prepareScheduleRows($schedule, $maxDayIndex));

    if (empty($scheduleRows[0])) {
        echo "<p><em>Program tablosu oluşturulamadı veya henüz ders eklenmemiş.</em></p>";
        return;
    }

    $days = ["Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"];
    $slots = $scheduleRows[0]; // Haftalık 1. hafta
    // Öğle arası yalnızca ders programında ayrı satır olarak gösterilir (sınavlar öğle saatinde de yapılabilir)
    $hasLunchRow = !$isExam;
    $coveredCells = []; // [rowIndex][dayIndex] => true (üstteki birleştirilmiş hücrenin kapsadığı hücreler)
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
            <?php foreach ($slots as $rowIndex => $slot):
                $slotStart = $slot['slotStartTime']->format('H:i');
                $slotEnd   = $slot['slotEndTime']->format('H:i');

                if ($hasLunchRow && ScheduleViewHelper::isLunchSlot($slot)):
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
                ?>
                <tr>
                    <td style="padding: 6px; border: 1px solid #ddd; background-color: #fafafa; text-align: center; font-size: 11px; font-weight: bold; white-space: nowrap;"><?= "{$slotStart} - {$slotEnd}" ?></td>
                    <?php for ($i = 0; $i <= $maxDayIndex; $i++):
                        if (isset($coveredCells[$rowIndex][$i])) {
                            continue;
                        }

                        $dayKey      = 'day' . $i;
                        $cellContent = $renderCellContent(ScheduleViewHelper::cellItems($slot['days'][$dayKey] ?? null));
                        // Ardışık saatlerde devam eden ders tek hücrede birleştirilir
                        $rowSpan = $cellContent !== '' ? ScheduleViewHelper::calculateRowSpan($slots, $rowIndex, $dayKey, $hasLunchRow) : 1;
                        for ($j = $rowIndex + 1; $j < $rowIndex + $rowSpan; $j++) {
                            $coveredCells[$j][$i] = true;
                        }
                        $bgColor = $cellContent !== '' ? '#fdfefe' : '#ffffff';
                        ?>
                        <td<?= $rowSpan > 1 ? " rowspan=\"{$rowSpan}\"" : '' ?> style="padding: 6px; border: 1px solid #ddd; background-color: <?= $bgColor ?>; vertical-align: middle; font-size: 11px; text-align: center;">
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
