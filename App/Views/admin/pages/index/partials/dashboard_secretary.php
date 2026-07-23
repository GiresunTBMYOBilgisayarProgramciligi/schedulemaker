<?php
/**
 * Dashboard Partial: Sekreter
 *
 * @var \App\Models\User $currentUser
 * @var array $stats  ['classrooms', 'buildings']
 */
?>

<!-- İstatistik Kartları -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-md-4">
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
    <div class="col-12 col-sm-6 col-md-4">
        <div class="small-box text-bg-info mb-0">
            <div class="inner">
                <h3><?= $stats['buildings'] ?? 0 ?></h3>
                <p>Bina</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-building-fill-gear"></i>
            </div>
            <a href="/admin/listbuildings" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Listele <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
    <?php if (!empty($currentUser->unit?->name)): ?>
    <div class="col-12 col-md-4">
        <div class="info-box bg-light shadow-sm mb-0">
            <span class="info-box-icon bg-secondary text-white">
                <i class="bi bi-bank2"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text text-muted">Birim</span>
                <span class="info-box-number fw-semibold"><?= htmlspecialchars($currentUser->unit->name) ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Hızlı Erişim -->
<div class="row g-3">
    <div class="col-12">
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-grid me-1"></i> Hızlı Erişim</h3>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <a href="/admin/listclassrooms" class="btn btn-outline-success w-100 py-3">
                            <i class="bi bi-door-open fs-4 d-block mb-1"></i>
                            Derslikler
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="/admin/listbuildings" class="btn btn-outline-info w-100 py-3">
                            <i class="bi bi-building-fill-gear fs-4 d-block mb-1"></i>
                            Binalar
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="/admin/profile" class="btn btn-outline-secondary w-100 py-3">
                            <i class="bi bi-person-badge fs-4 d-block mb-1"></i>
                            Profilim
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
