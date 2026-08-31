<?php
/**
 * Dashboard Partial: Mutemet (Payroll Officer)
 *
 * @var \App\Models\User $currentUser
 * @var array $stats  ['departments', 'programs', 'academics', 'lessons']
 * @var array $departments
 * @var array $programs
 * @var array $units
 */
?>

<!-- İstatistik Kartları -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-md-3">
        <div class="small-box text-bg-primary mb-0">
            <div class="inner">
                <h3><?= $stats['departments'] ?? 0 ?></h3>
                <p>Bölüm</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-buildings"></i>
            </div>
            <a href="/" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Programları Görüntüle <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="small-box text-bg-success mb-0">
            <div class="inner">
                <h3><?= $stats['programs'] ?? 0 ?></h3>
                <p>Program</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-building"></i>
            </div>
            <a href="/" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Programları Görüntüle <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="small-box text-bg-warning mb-0">
            <div class="inner">
                <h3><?= $stats['academics'] ?? 0 ?></h3>
                <p>Akademisyen</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-person-video3"></i>
            </div>
            <a href="/" class="small-box-footer link-dark link-underline-opacity-0 link-underline-opacity-50-hover">
                Hoca Programları <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="small-box text-bg-info mb-0">
            <div class="inner">
                <h3><?= $stats['lessons'] ?? 0 ?></h3>
                <p>Ders</p>
            </div>
            <div class="small-box-icon">
                <i class="bi bi-journal-text"></i>
            </div>
            <a href="/admin/listlessons" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                Ders Listesi <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        </div>
    </div>
</div>

<!-- Hızlı Erişim -->
<div class="row g-3">
    <div class="col-12">
        <div class="card card-outline card-primary shadow-sm h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="bi bi-grid me-1"></i> Hızlı İşlemler</h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="/" class="btn btn-outline-primary w-100 py-3 text-start d-flex align-items-center gap-3 h-100">
                            <i class="bi bi-calendar3 fs-2 text-primary flex-shrink-0"></i>
                            <div>
                                <strong class="d-block">Programı Görüntüle</strong>
                                <small class="text-muted">Haftalık Ders Programları</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="/admin/exportschedule" class="btn btn-outline-success w-100 py-3 text-start d-flex align-items-center gap-3 h-100">
                            <i class="bi bi-file-earmark-excel fs-2 text-success flex-shrink-0"></i>
                            <div>
                                <strong class="d-block">Program Dışa Aktar</strong>
                                <small class="text-muted">Excel / ICS Çıktısı Al</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="/admin/listlessons" class="btn btn-outline-info w-100 py-3 text-start d-flex align-items-center gap-3 h-100">
                            <i class="bi bi-journals fs-2 text-info flex-shrink-0"></i>
                            <div>
                                <strong class="d-block">Ders Listesi</strong>
                                <small class="text-muted">Ders ve Stajları Gör</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <a href="/admin/profile" class="btn btn-outline-secondary w-100 py-3 text-start d-flex align-items-center gap-3 h-100">
                            <i class="bi bi-person-badge fs-2 text-secondary flex-shrink-0"></i>
                            <div>
                                <strong class="d-block">Profilim</strong>
                                <small class="text-muted">Hesap Bilgilerim</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
