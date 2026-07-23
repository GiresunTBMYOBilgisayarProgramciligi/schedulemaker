<?php
/**
 * Dashboard Partial: Akademisyen / Araştırma Görevlisi
 *
 * @var \App\Models\User $currentUser
 * @var array $stats  ['lesson_count', 'weekly_hours']
 * @var string $scheduleHTML
 * @var \App\Models\Lesson[] $myLessons
 */
?>

<!-- Kişisel İstatistik Kartları -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-md-4">
        <div class="info-box shadow-sm mb-0">
            <span class="info-box-icon bg-primary text-white">
                <i class="bi bi-book-half"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">Üstlenilen Ders</span>
                <span class="info-box-number"><?= $stats['lesson_count'] ?? 0 ?></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4">
        <div class="info-box shadow-sm mb-0">
            <span class="info-box-icon bg-success text-white">
                <i class="bi bi-clock"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">Haftalık Ders Yükü</span>
                <span class="info-box-number"><?= $stats['weekly_hours'] ?? 0 ?> <small class="fs-6 text-muted">saat</small></span>
            </div>
        </div>
    </div>
    <?php if (!empty($currentUser->department?->name) || !empty($currentUser->program?->name)): ?>
    <div class="col-12 col-md-4">
        <div class="info-box shadow-sm mb-0">
            <span class="info-box-icon bg-secondary text-white">
                <i class="bi bi-buildings"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">
                    <?= !empty($currentUser->department?->name) ? 'Bölüm' : 'Program' ?>
                </span>
                <span class="info-box-number" style="font-size:0.95rem;">
                    <?= htmlspecialchars($currentUser->department?->name ?? $currentUser->program?->name ?? '-') ?>
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- Ders Programı -->
    <div class="col-12 col-xl-8">
        <div class="card card-outline card-primary h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-calendar-week me-1"></i> Ders Programım</h3>
                <div class="card-tools">
                    <a href="/admin/exportschedule" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download me-1"></i>Dışa Aktar
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($scheduleHTML)): ?>
                    <?= $scheduleHTML ?>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-1 opacity-50"></i>
                    <p class="mt-2">Ders programı henüz oluşturulmamış.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ders Listesi -->
    <div class="col-12 col-xl-4">
        <div class="card card-outline card-warning h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-journal-text me-1"></i> Derslerim</h3>
            </div>
            <div class="card-body p-0" style="overflow-y:auto;">
                <?php if (!empty($myLessons)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($myLessons as $lesson): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                        <div>
                            <div class="fw-semibold small"><?= htmlspecialchars($lesson->name ?? '') ?></div>
                            <div class="text-muted" style="font-size:0.75rem;">
                                <?= htmlspecialchars($lesson->program?->name ?? '') ?>
                                <?php if (!empty($lesson->hours)): ?>
                                    &bull; <?= $lesson->hours ?> saat/hafta
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="/admin/lesson/<?= $lesson->id ?>" class="btn btn-xs btn-outline-secondary py-0 px-1">
                            <i class="bi bi-eye"></i>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-journal-x fs-2 opacity-50"></i>
                    <p class="mt-2 small">Atanmış ders bulunamadı.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
