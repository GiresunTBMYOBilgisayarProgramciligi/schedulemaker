<?php
/**
 * Component: Birim / Bölüm / Program / Dönem Seçim Filtresi
 *
 * @var array $units
 * @var string|int|null $selectedUnitId
 * @var string|int|null $selectedDepartmentId
 * @var string|int|null $selectedProgramId
 * @var string|int|null $selectedSemesterNo
 * @var string|null $selectedSemester
 * @var string|null $dataAction
 * @var string|null $buttonId
 * @var string|null $buttonText
 * @var string|bool|null $dataOnlyTable
 * @var string|null $dataScheduleType
 * @var string|null $customButtonHtml
 * @var bool|null $showFormText
 */

use function App\Helpers\getMaxSemesterNo;
use function App\Helpers\getSemesterSelectOptions;
use function App\Helpers\getSettingValue;

$selectedUnitId = $selectedUnitId ?? $selected_unit_id ?? '';
$selectedDepartmentId = $selectedDepartmentId ?? $selected_department_id ?? $department_id ?? '';
$selectedProgramId = $selectedProgramId ?? $selected_program_id ?? $program_id ?? '';
$selectedSemesterNo = $selectedSemesterNo ?? $selected_semester_no ?? $semester_no ?? '';
$selectedSemester = $selectedSemester ?? $semester ?? getSettingValue('semester') ?? 'Güz';
$dataAction = $dataAction ?? null;
$buttonId = $buttonId ?? 'departmentAndProgramScheduleButton';
$buttonText = $buttonText ?? 'Göster';
$dataOnlyTable = isset($dataOnlyTable) ? (string)$dataOnlyTable : null;
$dataScheduleType = $dataScheduleType ?? null;
$customButtonHtml = $customButtonHtml ?? null;
$showFormText = $showFormText ?? false;

$hasProgramSelected = !empty($selectedProgramId) && (string)$selectedProgramId !== '0';
$hasUnitSelected = !empty($selectedUnitId) && (string)$selectedUnitId !== '0';

$semesterOptions = [];
$maxSemester = null;

if ($hasProgramSelected || ($showFormText && $hasUnitSelected)) {
    $maxSemester = getMaxSemesterNo(
        $hasProgramSelected ? (int)$selectedProgramId : null,
        !empty($selectedDepartmentId) ? (int)$selectedDepartmentId : null,
        $hasUnitSelected ? (int)$selectedUnitId : null
    );
    $semesterOptions = getSemesterSelectOptions($selectedSemester, $maxSemester);
}
?>
<div class="row g-2 g-md-3">
    <div class="col-12 col-md-3">
        <select class="form-select tom-select" id="unit_id" name="unit_id"<?= !empty($dataAction) ? ' data-action="' . htmlspecialchars($dataAction) . '"' : '' ?>>
            <option value="">Birim Seçiniz</option>
            <?php if (!empty($units)): ?>
                <?php foreach ($units as $unit): ?>
                    <?php $unitMax = getMaxSemesterNo(null, null, $unit->id); ?>
                    <option value="<?= $unit->id ?>" data-type="<?= htmlspecialchars((string)($unit->type ?? '')) ?>" data-max-semester="<?= $unitMax ?>" <?= ((string)$selectedUnitId !== '' && (string)$selectedUnitId === (string)$unit->id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($unit->name) ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <?php if ($showFormText): ?>
            <div class="form-text">
                Birim seçilmezse tüm yetkili birimler dışa aktarılır
            </div>
        <?php endif; ?>
    </div>
    <div class="col-12 col-md-3">
        <select class="form-select tom-select" id="department_id" name="department_id"<?= !empty($dataAction) ? ' data-action="' . htmlspecialchars($dataAction) . '"' : '' ?> data-selected="<?= htmlspecialchars((string)$selectedDepartmentId) ?>">
            <option value="0">İlk olarak Birim Seçiniz</option>
        </select>
        <?php if ($showFormText): ?>
            <div class="form-text">
                Bölüm seçilmezse birime ait tüm programlar dışa aktarılır
            </div>
        <?php endif; ?>
    </div>
    <div class="col-12 col-md-3">
        <select class="form-select" id="program_id" name="program_id" data-selected="<?= htmlspecialchars((string)$selectedProgramId) ?>">
            <option value="0">İlk olarak Bölüm seçiniz</option>
        </select>
        <?php if ($showFormText): ?>
            <div class="form-text">
                Program seçilmezse bölüme ait tüm programlar dışa aktarılır
            </div>
        <?php endif; ?>
    </div>
    <div class="col-12 col-md-3">
        <div class="input-group">
            <select class="form-select" id="semester_no" name="semester_no" data-selected="<?= htmlspecialchars((string)$selectedSemesterNo) ?>"<?= $maxSemester !== null ? ' data-max-semester="' . $maxSemester . '"' : '' ?>>
                <option value=""><?= !empty($semesterOptions) ? 'Tüm Yarıyıllar / Sınıflar' : 'İlk olarak Program seçiniz' ?></option>
                <?php foreach ($semesterOptions as $semNo => $semLabel): ?>
                    <option value="<?= $semNo ?>" <?= ((string)$selectedSemesterNo !== '' && (string)$selectedSemesterNo === (string)$semNo) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($semLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($customButtonHtml)): ?>
                <?= $customButtonHtml ?>
            <?php else: ?>
                <button type="button" class="btn btn-primary" id="<?= htmlspecialchars($buttonId) ?>"<?= $dataOnlyTable !== null ? ' data-only-table="' . htmlspecialchars($dataOnlyTable) . '"' : '' ?><?= !empty($dataScheduleType) ? ' data-schedule-type="' . htmlspecialchars($dataScheduleType) . '"' : '' ?>>
                    <?= htmlspecialchars($buttonText) ?>
                </button>
            <?php endif; ?>
        </div>
        <?php if ($showFormText): ?>
            <div class="form-text">
                Dönem seçilmezse tüm dönemler dışa aktarılır
            </div>
        <?php endif; ?>
    </div>
</div>
