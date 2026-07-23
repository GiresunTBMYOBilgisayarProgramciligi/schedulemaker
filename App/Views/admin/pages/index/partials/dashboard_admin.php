<?php
/**
 * Dashboard Partial: Yönetici Grubu (admin / manager / submanager)
 *
 * @var \App\Models\User $currentUser
 * @var array $stats  ['units', 'academics', 'classrooms', 'lessons', 'departments', 'programs']
 * @var \App\Models\Log[] $recentLogs
 * @var \App\Models\Program[] $programs
 * @var \App\Models\Unit[] $units
 * @var \App\Models\Department[] $departments
 */
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

<div class="row g-3">

    <!-- ===================== Listeler (sol kolon) ===================== -->
    <?php $isAdmin = $currentUser->role === \App\Enums\UserRole::Admin->value; ?>
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
                                <td class="fw-semibold"><?= htmlspecialchars($unit->name) ?></td>
                                <td class="text-end">
                                    <a href="/admin/unit/<?= $unit->id ?>" class="btn btn-xs btn-outline-secondary py-0 px-1">
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
                                <td class="fw-semibold"><?= htmlspecialchars($dept->name) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($dept->unit?->name ?? '-') ?></td>
                                <td class="text-muted small">
                                    <?php if (!empty($dept->chairperson)): ?>
                                        <a href="/admin/profile/<?= $dept->chairperson->id ?>">
                                            <?= htmlspecialchars($dept->chairperson->getFullName()) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Atanmamış</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/department/<?= $dept->id ?>" class="btn btn-xs btn-outline-secondary py-0 px-1">
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
                                <td class="fw-semibold"><?= htmlspecialchars($program->name) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($program->department?->name ?? '-') ?></td>
                                <td class="text-center"><?= count($program->lecturers ?? []) ?></td>
                                <td class="text-center"><?= count($program->lessons ?? []) ?></td>
                                <td class="text-end">
                                    <a href="/admin/program/<?= $program->id ?>" class="btn btn-xs btn-outline-secondary py-0 px-1">
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
            </div>
            <div class="card-body p-0" style="overflow-y:auto; max-height:890px;">
                <?php if (!empty($recentLogs)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentLogs as $log): ?>
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1 me-2" style="min-width:0;">
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <?= $log->getLevelHtml() ?>
                                    <span class="text-muted small text-truncate"><?= htmlspecialchars($log->channel ?? '') ?></span>
                                </div>
                                <p class="mb-0 small text-truncate" title="<?= htmlspecialchars($log->message ?? '') ?>">
                                    <?= htmlspecialchars($log->message ?? '') ?>
                                </p>
                                <?php if (!empty($log->username)): ?>
                                    <span class="text-muted" style="font-size:0.7rem;">
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($log->username) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-muted text-nowrap" style="font-size:0.7rem;">
                                <?= htmlspecialchars(substr($log->created_at ?? '', 0, 16)) ?>
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
