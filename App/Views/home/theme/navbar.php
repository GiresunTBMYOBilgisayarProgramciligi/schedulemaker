<?php
/**
 * @var \App\Models\User|null $currentUser Oturum açmış kullanıcı
 */
use function App\Helpers\getCurrentYearAndSemester;
use function App\Helpers\getSettingValue;

$currentPeriod = '';
try {
    $currentPeriod = getCurrentYearAndSemester();
} catch (\Throwable $e) {
    $currentPeriod = (getSettingValue('academic_year') ?? '') . ' ' . (getSettingValue('semester') ?? '');
}
?>
<!--begin::Header-->
<nav class="app-header navbar navbar-expand-lg bg-body border-bottom shadow-sm sticky-top">
    <!--begin::Container-->
    <div class="container-fluid px-3 px-lg-4">
        <!--begin::Brand / University Title-->
        <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none py-1" href="/">
            <img src="/assets/images/gru_logo_sm.png" alt="Giresun Üniversitesi Logo" width="40" height="40" class="img-fluid rounded-2 shadow-xs" />
            <div class="d-flex flex-column">
                <span class="fw-bold tracking-tight text-body lh-1 fs-6">GİRESUN ÜNİVERSİTESİ</span>
                <span class="text-muted small fw-medium" style="font-size: 0.75rem;">Ders & Sınav Programı Bilgi Sistemi</span>
            </div>
        </a>
        <!--end::Brand-->

        <!--begin::Navbar Toggler for Mobile-->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPublicContent" aria-controls="navbarPublicContent" aria-expanded="false" aria-label="Menüyü Aç">
            <span class="navbar-toggler-icon"></span>
        </button>
        <!--end::Navbar Toggler-->

        <!--begin::Navbar Collapse Content-->
        <div class="collapse navbar-collapse mt-2 mt-lg-0" id="navbarPublicContent">
            <!--begin::Right Navbar Links-->
            <ul class="navbar-nav ms-auto align-items-lg-center gap-1 gap-lg-2">
                <!--begin::Quick Info Modal Trigger-->
                <li class="nav-item">
                    <button class="nav-link btn btn-link text-decoration-none px-2 py-1" type="button" data-bs-toggle="modal" data-bs-target="#quickHelpModal" title="Kullanım İpuçları & Yardım">
                        <i class="bi bi-question-circle text-muted fs-5"></i>
                        <span class="d-lg-none ms-1">Yardım & İpuçları</span>
                    </button>
                </li>
                <!--end::Quick Info Modal Trigger-->

                <!--begin::Fullscreen Toggle-->
                <li class="nav-item d-none d-md-block">
                    <a class="nav-link px-2 py-1" href="#" data-lte-toggle="fullscreen" title="Tam Ekran">
                        <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen fs-6"></i>
                        <i data-lte-icon="minimize" class="bi bi-fullscreen-exit fs-6" style="display: none"></i>
                    </a>
                </li>
                <!--end::Fullscreen Toggle-->

                <!--begin::Color Mode Toggle-->
                <li class="nav-item dropdown">
                    <a class="nav-link px-2 py-1" href="#" id="bd-theme" aria-label="Tema Seçimi" data-bs-toggle="dropdown" aria-expanded="false" title="Görünüm Teması">
                        <i class="bi bi-sun-fill" data-lte-theme-icon="light"></i>
                        <i class="bi bi-moon-fill d-none" data-lte-theme-icon="dark"></i>
                        <i class="bi bi-circle-half d-none" data-lte-theme-icon="auto"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="bd-theme" style="--bs-dropdown-min-width: 8.5rem">
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="light" aria-pressed="false">
                                <i class="bi bi-sun-fill text-warning"></i> Açık Tema
                                <i class="bi bi-check-lg ms-auto d-none"></i>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="dark" aria-pressed="false">
                                <i class="bi bi-moon-stars-fill text-primary"></i> Koyu Tema
                                <i class="bi bi-check-lg ms-auto d-none"></i>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="auto" aria-pressed="false">
                                <i class="bi bi-circle-half text-secondary"></i> Sistem (Otomatik)
                                <i class="bi bi-check-lg ms-auto d-none"></i>
                            </button>
                        </li>
                    </ul>
                </li>
                <!--end::Color Mode Toggle-->

                <li class="nav-item d-none d-lg-block mx-1">
                    <div class="vr h-100 opacity-25"></div>
                </li>

                <!--begin::User / Login Action-->
                <?php if ($currentUser): ?>
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2 py-1 px-2 rounded-pill bg-body-secondary" data-bs-toggle="dropdown">
                            <img src="<?= $currentUser->getGravatarURL(40) ?>" class="rounded-circle shadow-xs" width="28" height="28" alt="Kullanıcı Resmi" />
                            <span class="fw-semibold text-truncate small" style="max-width: 140px;"><?= htmlspecialchars($currentUser->getFullName()) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold"><?= htmlspecialchars($currentUser->getFullName()) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($currentUser->email ?? '') ?></div>
                                <span class="badge text-bg-primary mt-1"><?= htmlspecialchars(strtoupper($currentUser->role ?? 'Yetkili')) ?></span>
                            </li>
                            <li>
                                <a href="/admin" class="dropdown-item d-flex align-items-center gap-2 py-2">
                                    <i class="bi bi-speedometer2 text-primary"></i>
                                    <span>Yönetim Paneli</span>
                                </a>
                            </li>
                            <li>
                                <a href="/admin/profile" class="dropdown-item d-flex align-items-center gap-2 py-2">
                                    <i class="bi bi-person-gear text-secondary"></i>
                                    <span>Profilim</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a href="/auth/logout" class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span>Çıkış Yap</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item mt-2 mt-lg-0">
                        <a href="/admin" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 d-inline-flex align-items-center gap-2 shadow-xs">
                            <i class="bi bi-shield-lock"></i>
                            <span>Yönetim Paneli Girişi</span>
                        </a>
                    </li>
                <?php endif; ?>
                <!--end::User / Login Action-->
            </ul>
            <!--end::Right Navbar Links-->
        </div>
        <!--end::Navbar Collapse Content-->
    </div>
    <!--end::Container-->
</nav>
<!--end::Header-->