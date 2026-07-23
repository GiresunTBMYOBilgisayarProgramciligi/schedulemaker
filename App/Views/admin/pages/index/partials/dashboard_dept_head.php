<?php
/**
 * Dashboard Partial: Bölüm Başkanı
 *
 * @var \App\Models\User $currentUser
 * @var \App\Models\Department|null $department
 * @var array $stats  ['programs', 'academics', 'lessons']
 * @var string $scheduleHTML
 */
?>

<!-- Bölüm Özet Kartları -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <div class="small-box text-bg-primary mb-0">
            <div class="inner">
                <h3><?= $stats['programs'] ?? 0 ?></h3>
                <p>Program</p>
            </div>
            <div class="small-box-icon"><i class="bi bi-building"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="small-box text-bg-success mb-0">
            <div class="inner">
                <h3><?= $stats['academics'] ?? 0 ?></h3>
                <p>Akademisyen</p>
            </div>
            <div class="small-box-icon"><i class="bi bi-person-video3"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="small-box text-bg-warning mb-0">
            <div class="inner">
                <h3><?= $stats['lessons'] ?? 0 ?></h3>
                <p>Ders</p>
            </div>
            <div class="small-box-icon"><i class="bi bi-book"></i></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Bölüm Programları -->
    <?php if (!empty($department)): ?>
    <div class="col-12 col-xl-5">
        <div class="card card-outline card-primary h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-buildings me-1"></i>
                    <?= htmlspecialchars($department->name ?? 'Bölümüm') ?>
                </h3>
                <div class="card-tools">
                    <a href="/admin/department/<?= $currentUser->department_id ?>" class="btn btn-sm btn-outline-primary">
                        Bölüm Detayı
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($department->programs)): ?>
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Program</th>
                            <th class="text-center">Akademisyen</th>
                            <th class="text-center">Ders</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($department->programs as $prog): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($prog->name) ?></td>
                            <td class="text-center"><?= count($prog->lecturers ?? []) ?></td>
                            <td class="text-center"><?= count($prog->lessons ?? []) ?></td>
                            <td class="text-end">
                                <a href="/admin/program/<?= $prog->id ?>" class="btn btn-xs btn-outline-secondary py-0 px-1">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted text-center py-3">Bölüme bağlı program bulunamadı.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Ders Programı -->
    <div class="col-12 col-xl-7">
        <div class="card card-outline card-info h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-calendar-week me-1"></i> Ders Programım</h3>
                <div class="card-tools">
                    <a href="/admin/editschedule" class="btn btn-sm btn-outline-info">
                        Programı Düzenle
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($scheduleHTML)): ?>
                    <?= $scheduleHTML ?>
                <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x fs-2"></i>
                    <p class="mt-2">Ders programı henüz oluşturulmamış.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Akademisyen Listesi -->
<?php if (!empty($department->users)): ?>
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-people me-1"></i> Bölüm Akademisyenleri</h3>
                <div class="card-tools">
                    <a href="/admin/listusers" class="btn btn-sm btn-outline-secondary">Tüm Kullanıcılar</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ad Soyad</th>
                            <th>Ünvan</th>
                            <th>Program</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($department->users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u->getFullName()) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($u->title ?? '') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($u->program?->name ?? '-') ?></td>
                            <td class="text-end">
                                <a href="/admin/profile/<?= $u->id ?>" class="btn btn-xs btn-outline-secondary py-0 px-1">
                                    <i class="bi bi-person"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
