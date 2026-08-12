<?php
/**
 * Component: Birim / Bölüm / Program Seçim Filtresi
 *
 * @var array $units
 * @var string|int|null $selectedUnitId
 * @var string|int|null $selectedDepartmentId
 * @var string|int|null $selectedProgramId
 * @var string|null $dataAction
 * @var string|null $buttonId
 * @var string|null $buttonText
 * @var string|bool|null $dataOnlyTable
 * @var string|null $dataScheduleType
 * @var string|null $customButtonHtml
 * @var bool|null $showFormText
 */

$selectedUnitId = $selectedUnitId ?? $selected_unit_id ?? '';
$selectedDepartmentId = $selectedDepartmentId ?? $selected_department_id ?? $department_id ?? '';
$selectedProgramId = $selectedProgramId ?? $selected_program_id ?? $program_id ?? '';
$dataAction = $dataAction ?? null;
$buttonId = $buttonId ?? 'departmentAndProgramScheduleButton';
$buttonText = $buttonText ?? 'Göster';
$dataOnlyTable = isset($dataOnlyTable) ? (string)$dataOnlyTable : null;
$dataScheduleType = $dataScheduleType ?? null;
$customButtonHtml = $customButtonHtml ?? null;
$showFormText = $showFormText ?? false;
?>
<div class="row">
    <div class="col-12 col-md-4">
        <select class="form-select tom-select" id="unit_id" name="unit_id"<?= !empty($dataAction) ? ' data-action="' . htmlspecialchars($dataAction) . '"' : '' ?>>
            <option value="">Birim Seçiniz</option>
            <?php if (!empty($units)): ?>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit->id ?>" <?= ((string)$selectedUnitId !== '' && (string)$selectedUnitId === (string)$unit->id) ? 'selected' : '' ?>>
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
    <div class="col-12 col-md-4">
        <select class="form-select tom-select" id="department_id" name="department_id"<?= !empty($dataAction) ? ' data-action="' . htmlspecialchars($dataAction) . '"' : '' ?> data-selected="<?= htmlspecialchars((string)$selectedDepartmentId) ?>">
            <option value="0">İlk olarak Birim Seçiniz</option>
        </select>
        <?php if ($showFormText): ?>
            <div class="form-text">
                Bölüm seçilmezse birime ait tüm programlar dışa aktarılır
            </div>
        <?php endif; ?>
    </div>
    <div class="col-12 col-md-4">
        <div class="input-group">
            <select class="form-select" id="program_id" name="program_id" data-selected="<?= htmlspecialchars((string)$selectedProgramId) ?>">
                <option value="0">İlk olarak Bölüm seçiniz</option>
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
                Program seçilmezse bölüme ait tüm programlar dışa aktarılır
            </div>
        <?php endif; ?>
    </div>
</div>
