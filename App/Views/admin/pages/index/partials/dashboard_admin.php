<?php
/**
 * Dashboard Partial: Yönetici Grubu (admin / manager / submanager)
 *
 * @var User         $currentUser
 * @var array        $stats  ['units', 'academics', 'classrooms', 'lessons', 'departments', 'programs']
 * @var object[]     $recentLogs
 * @var Program[]    $programs
 * @var Unit[]       $units
 * @var Department[] $departments
 * @var array        $examDutyStats   UserRepository::getExamDutyStats() çıktısı
 * @var array        $examDutyFilters ['academic_year', 'semester', 'unit_id', 'department_id']
 * @var array        $examDutySummary ['total', 'assigned_count', 'unassigned_count', 'average', 'max']
 */

use App\Core\View;
use App\Enums\ExamType;
use App\Enums\UserRole;
use App\Helpers\TimeHelper;
use App\Helpers\LogViewHelper;
use function App\Helpers\e;
?>

<!-- İstatistik Kartları -->
<div class="row g-3 mb-4">
    <!-- Birim -->
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="small-box text-bg-secondary mb-0">
            <div class="inner">
                <h3><?= $stats['units'] ?? 0 ?></h3>
                <p>Birim</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-bank"></i>
            </div>
            <a href="/admin/listunits" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Listele <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
    <!-- Öğretim Elemanı -->
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="small-box text-bg-primary mb-0">
            <div class="inner">
                <h3><?= $stats['academics'] ?? 0 ?></h3>
                <p>Öğretim Elemanı</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-person-video3"></i>
            </div>
            <a href="/admin/listusers" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Listele <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
    <!-- Derslik -->
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="small-box text-bg-success mb-0">
            <div class="inner">
                <h3><?= $stats['classrooms'] ?? 0 ?></h3>
                <p>Derslik</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-door-open"></i>
            </div>
            <a href="/admin/listclassrooms" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Listele <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
    <!-- Ders -->
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="small-box text-bg-warning mb-0">
            <div class="inner">
                <h3><?= $stats['lessons'] ?? 0 ?></h3>
                <p>Ders</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-book"></i>
            </div>
            <a href="/admin/listlessons" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Listele <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
    <!-- Bölüm -->
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="small-box text-bg-danger mb-0">
            <div class="inner">
                <h3><?= $stats['departments'] ?? 0 ?></h3>
                <p>Bölüm</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-buildings"></i>
            </div>
            <a href="/admin/listdepartments" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Listele <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
    <!-- Program -->
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="small-box text-bg-info mb-0">
            <div class="inner">
                <h3><?= $stats['programs'] ?? 0 ?></h3>
                <p>Program</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-building"></i>
            </div>
            <a href="/admin/listprograms" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Listele <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
</div>
<!-- /row istatistik -->

<?php
// ===================== Sınav Görev İstatistikleri =====================
$examDutyStats   = $examDutyStats ?? [];
$examDutyFilters = $examDutyFilters ?? [];
$examDutySummary = $examDutySummary ?? ['total' => 0, 'assigned_count' => 0, 'unassigned_count' => 0, 'average' => 0, 'max' => 0];
$dutyYear        = $examDutyFilters['academic_year'] ?? '';
$dutySemester    = $examDutyFilters['semester'] ?? '';
$dutyWithTasks   = array_filter($examDutyStats, fn($r) => $r['total'] > 0);
?>
<div class="card card-outline card-info mb-4 exam-duty" id="examDutyCard">
    <div class="card-header" role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapseExamDuty"
         aria-expanded="true"
         aria-controls="collapseExamDuty"
         style="cursor:pointer;">
        <h3 class="card-title">
            <i class="bi bi-clipboard-data me-1"></i> Sınav Gözetmenlik Görevleri
            <span class="text-muted small ms-1">
                <?= e($dutyYear . ' ' . $dutySemester) ?>
            </span>
        </h3>
        <div class="card-tools">
            <i class="bi bi-chevron-up collapse-icon"></i>
        </div>
    </div>
    <div class="collapse show" id="collapseExamDuty">
        <div class="card-body">
            <!-- Filtre -->
            <form method="get" action="/admin#examDutyCard" class="row g-2 g-md-3 mb-3 align-items-start" id="examDutyFilterForm">
                <div class="col-6 col-md-2">
                    <select class="form-select" name="academic_year" aria-label="Akademik Yıl">
                        <?php for ($year = 2023; $year <= (int)date('Y'); $year++): ?>
                            <?php $yearValue = $year . ' - ' . ($year + 1); ?>
                            <option value="<?= e($yearValue) ?>" <?= $dutyYear === $yearValue ? 'selected' : '' ?>><?= e($yearValue) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select" name="semester" aria-label="Dönem">
                        <?php foreach (['Güz', 'Bahar', 'Yaz'] as $semesterOption): ?>
                            <option value="<?= e($semesterOption) ?>" <?= $dutySemester === $semesterOption ? 'selected' : '' ?>><?= e($semesterOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <?= View::renderComponent('schedules/_programSelector', [
                        'units'                => $units ?? [],
                        'selectedUnitId'       => $examDutyFilters['unit_id'] ?? '',
                        'selectedDepartmentId' => $examDutyFilters['department_id'] ?? '',
                        'showProgram'          => false,
                        'showSemesterNo'       => false,
                        'showButton'           => false,
                    ]) ?>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-funnel me-1"></i>Filtrele
                    </button>
                    <a href="/admin" class="btn btn-outline-secondary" title="Filtreyi temizle">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>

            <!-- Özet -->
            <div class="row g-2 mb-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="border rounded py-2">
                        <div class="fs-4 fw-semibold"><?= $examDutySummary['total'] ?></div>
                        <div class="text-muted small">Toplam Görev</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded py-2">
                        <div class="fs-4 fw-semibold"><?= $examDutySummary['assigned_count'] ?></div>
                        <div class="text-muted small">Görevli Hoca</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded py-2">
                        <div class="fs-4 fw-semibold"><?= $examDutySummary['average'] ?></div>
                        <div class="text-muted small">Hoca Başına Ortalama</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded py-2">
                        <div class="fs-4 fw-semibold"><?= $examDutySummary['unassigned_count'] ?></div>
                        <div class="text-muted small">Görevi Olmayan</div>
                    </div>
                </div>
            </div>

            <?php if (empty($dutyWithTasks)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-clipboard-x fs-2"></i>
                    <p class="mt-2 mb-0">Bu dönem için atanmış gözetmenlik görevi bulunamadı.</p>
                </div>
            <?php else: ?>
            <div class="row g-3">
                <!-- Grafik -->
                <div class="col-12 col-xl-7">
                    <div class="d-flex flex-wrap gap-3 mb-2 small text-body-secondary">
                        <?php foreach (ExamType::cases() as $examType): ?>
                            <span><span class="duty-swatch seg-<?= $examType->value ?>"></span><?= e($examType->label()) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="duty-chart">
                        <?php foreach ($dutyWithTasks as $row): ?>
                        <?php
                            $typeSummary = implode(', ', array_map(
                                fn(ExamType $type) => $type->label() . ': ' . $row['by_type'][$type->value],
                                ExamType::cases()
                            ));
                            $tooltip = $row['full_name'] . ' — ' . $typeSummary
                                . ' (Toplam ' . $row['total'] . ', ' . TimeHelper::formatDuration($row['total_minutes']) . ')';
                        ?>
                        <div class="duty-row" title="<?= e($tooltip) ?>">
                            <div class="duty-name"><?= e($row['full_name']) ?></div>
                            <div class="duty-track" style="width: <?= round($row['total'] / $examDutySummary['max'] * 100, 2) ?>%;">
                                <?php foreach ($row['by_type'] as $type => $count): ?>
                                    <?php if ($count > 0): ?>
                                    <div class="duty-seg seg-<?= e($type) ?>" style="flex: <?= $count ?> 0 0;"></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="duty-value"><?= $row['total'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Liste -->
                <div class="col-12 col-xl-5">
                    <div class="duty-table">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Öğretim Elemanı</th>
                                    <?php foreach (ExamType::cases() as $examType): ?>
                                        <th class="text-center"><?= e($examType->label()) ?></th>
                                    <?php endforeach; ?>
                                    <th class="text-center">Toplam</th>
                                    <th class="text-end">Süre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($examDutyStats as $row): ?>
                                <tr class="<?= $row['total'] === 0 ? 'text-muted' : '' ?>">
                                    <td>
                                        <a href="/admin/profile/<?= $row['id'] ?>" class="fw-semibold"><?= e($row['full_name']) ?></a>
                                        <?php if (!empty($row['department_name'])): ?>
                                            <div class="text-muted duty-department"><?= e($row['department_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($row['by_type'] as $count): ?>
                                        <td class="text-center"><?= $count ?></td>
                                    <?php endforeach; ?>
                                    <td class="text-center fw-semibold"><?= $row['total'] ?></td>
                                    <td class="text-end small text-nowrap"><?= TimeHelper::formatDuration($row['total_minutes']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- ===================== Listeler (sol kolon) ===================== -->
    <?php $isAdmin = $currentUser->role === UserRole::Admin->value; ?>
    <div class="col-12 col-xl-<?= $isAdmin ? '7' : '12' ?>">

        <!-- Birim Listesi (collapsible) -->
        <div class="card card-outline card-secondary mb-3">
            <div class="card-header" role="button"
                 data-bs-toggle="collapse"
                 data-bs-target="#collapseUnits"
                 aria-expanded="true"
                 aria-controls="collapseUnits"
                 style="cursor:pointer;">
                <h3 class="card-title">
                    <i class="bi bi-bank me-1"></i> Birimler
                    <span class="badge bg-secondary ms-1"><?= count($units ?? []) ?></span>
                </h3>
                <div class="card-tools">
                    <a href="/admin/listunits" class="btn btn-sm btn-outline-secondary me-1"
                       onclick="event.stopPropagation()">Tümünü Gör</a>
                    <i class="bi bi-chevron-up collapse-icon"></i>
                </div>
            </div>
            <div class="collapse show" id="collapseUnits">
                <div class="card-body p-0" style="overflow-y:auto; max-height:280px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Birim Adı</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units ?? [] as $unit): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($unit->name) ?></td>
                                <td class="text-end">
                                    <a href="/admin/unit/<?= (int)$unit->id ?>" class="btn btn-xs btn-outline-secondary py-0 px-1">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($units)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">Birim bulunamadı.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bölüm Listesi (collapsible) -->
        <div class="card card-outline card-danger mb-3">
            <div class="card-header" role="button"
                 data-bs-toggle="collapse"
                 data-bs-target="#collapseDepartments"
                 aria-expanded="true"
                 aria-controls="collapseDepartments"
                 style="cursor:pointer;">
                <h3 class="card-title">
                    <i class="bi bi-buildings me-1"></i> Bölümler
                    <span class="badge bg-danger ms-1"><?= count($departments ?? []) ?></span>
                </h3>
                <div class="card-tools">
                    <a href="/admin/listdepartments" class="btn btn-sm btn-outline-danger me-1"
                       onclick="event.stopPropagation()">Tümünü Gör</a>
                    <i class="bi bi-chevron-up collapse-icon"></i>
                </div>
            </div>
            <div class="collapse show" id="collapseDepartments">
                <div class="card-body p-0" style="overflow-y:auto; max-height:280px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Bölüm</th>
                                <th>Birim</th>
                                <th>Başkan</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments ?? [] as $dept): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($dept->name) ?></td>
                                <td class="text-muted small"><?= e($dept->unit?->name ?? '-') ?></td>
                                <td class="text-muted small">
                                    <?php if (!empty($dept->chairperson)): ?>
                                        <a href="/admin/profile/<?= (int)$dept->chairperson->id ?>">
                                            <?= e($dept->chairperson->getFullName()) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Atanmamış</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/department/<?= (int)$dept->id ?>" class="btn btn-xs btn-outline-secondary py-0 px-1">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($departments)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Bölüm bulunamadı.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Aktif Programlar (collapsible) -->
        <div class="card card-outline card-primary mb-3">
            <div class="card-header" role="button"
                 data-bs-toggle="collapse"
                 data-bs-target="#collapsePrograms"
                 aria-expanded="true"
                 aria-controls="collapsePrograms"
                 style="cursor:pointer;">
                <h3 class="card-title">
                    <i class="bi bi-building me-1"></i> Aktif Programlar
                    <span class="badge bg-primary ms-1"><?= count($programs ?? []) ?></span>
                </h3>
                <div class="card-tools">
                    <a href="/admin/listprograms" class="btn btn-sm btn-outline-primary me-1"
                       onclick="event.stopPropagation()">Tümünü Gör</a>
                    <i class="bi bi-chevron-up collapse-icon"></i>
                </div>
            </div>
            <div class="collapse show" id="collapsePrograms">
                <div class="card-body p-0" style="overflow-y:auto; max-height:280px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Program</th>
                                <th>Bölüm</th>
                                <th class="text-center">Akademisyen</th>
                                <th class="text-center">Ders</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programs ?? [] as $program): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($program->name) ?></td>
                                <td class="text-muted small"><?= e($program->department?->name ?? '-') ?></td>
                                <td class="text-center"><?= count($program->lecturers ?? []) ?></td>
                                <td class="text-center"><?= count($program->lessons ?? []) ?></td>
                                <td class="text-end">
                                    <a href="/admin/program/<?= (int)$program->id ?>" class="btn btn-xs btn-outline-secondary py-0 px-1">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($programs)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Aktif program bulunamadı.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <!-- /sol kolon -->

    <?php if ($isAdmin): ?>
    <!-- Son Aktiviteler (Loglar) -->
    <div class="col-12 col-xl-5">
        <div class="card card-outline card-secondary h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-clock-history me-1"></i> Son Sistem Aktiviteleri</h3>
                <div class="card-tools">
                    <a href="/admin/logs" class="btn btn-sm btn-outline-secondary me-1"
                       onclick="event.stopPropagation()">Tümünü Gör</a>
                    <i class="bi bi-chevron-up collapse-icon"></i>
                </div>
            </div>
            <div class="card-body p-0" style="overflow-y:auto; max-height:890px;">
                <?php if (!empty($recentLogs)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentLogs as $log): ?>
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1 me-2" style="min-width:0;">
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <?= LogViewHelper::renderLevelBadge($log) ?>
                                    <span class="text-muted small text-truncate"><?= e($log->channel ?? '') ?></span>
                                </div>
                                <p class="mb-0 small text-truncate" title="<?= e($log->message ?? '') ?>">
                                    <?= e($log->message ?? '') ?>
                                </p>
                                <?php if (!empty($log->username)): ?>
                                    <span class="text-muted" style="font-size:0.7rem;">
                                        <i class="bi bi-person"></i> <?= e($log->username) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-muted text-nowrap" style="font-size:0.7rem;">
                                <?= e(substr($log->created_at ?? '', 0, 16)) ?>
                            </span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-journal-x fs-2"></i>
                    <p class="mt-2">Log kaydı bulunamadı.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Collapse açık/kapalı ikonunu güncelle
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (el) {
    var targetId = el.getAttribute('data-bs-target');
    var icon = el.querySelector('.collapse-icon');

    document.querySelector(targetId).addEventListener('show.bs.collapse', function () {
        if (icon) icon.className = 'bi bi-chevron-up collapse-icon';
    });
    document.querySelector(targetId).addEventListener('hide.bs.collapse', function () {
        if (icon) icon.className = 'bi bi-chevron-down collapse-icon';
    });
});
</script>
