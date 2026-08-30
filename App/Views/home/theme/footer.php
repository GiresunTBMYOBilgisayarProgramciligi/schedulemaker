<!--begin::Institutional Footer-->
<footer class="app-footer bg-body-tertiary border-top py-4 mt-auto">
    <div class="container-fluid px-3 px-lg-4">
        <div class="row gy-3 align-items-center">
            <!-- Left: University Branding & Info -->
            <div class="col-12 col-md-4 text-center text-md-start">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                    <img src="/assets/images/gru_logo_xs.png" alt="GRÜ Logo" width="28" height="28" class="img-fluid" />
                    <span class="fw-bold text-body">Giresun Üniversitesi</span>
                </div>
                <p class="text-muted small mb-0">
                    Ders ve Sınav Programı Bilgi Sistemi &copy; <?= date('Y') ?>. Tüm hakları saklıdır.
                </p>
            </div>

            <!-- Center: Fast Academic & Legal Links -->
            <div class="col-12 col-md-4 text-center">
                <div class="d-flex flex-wrap justify-content-center gap-3 small mb-1">
                    <a href="https://giresun.edu.tr" target="_blank" rel="noopener noreferrer" class="text-secondary text-decoration-none hover-primary">
                        <i class="bi bi-globe me-1"></i>giresun.edu.tr
                    </a>
                    <a href="/admin" class="text-secondary text-decoration-none hover-primary">
                        <i class="bi bi-speedometer2 me-1"></i>Yönetim
                    </a>
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2 small text-muted">
                    <a href="/legal/kvkk" class="text-secondary text-decoration-none hover-primary">
                        <i class="bi bi-shield-lock me-1"></i>KVKK Aydınlatma Metni
                    </a>
                    <span>&bull;</span>
                    <a href="/legal/privacy" class="text-secondary text-decoration-none hover-primary">
                        <i class="bi bi-shield-check me-1"></i>Gizlilik & Çerezler
                    </a>
                </div>
            </div>

            <!-- Right: Developer Signature & System Version -->
            <div class="col-12 col-md-4 text-center text-md-end">
                <div class="d-inline-flex flex-column align-items-center align-items-md-end">
                    <div class="developer-badge d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-body border shadow-xs mb-1">
                        <i class="bi bi-code-slash text-primary"></i>
                        <span class="small text-muted">Geliştirici:</span>
                        <span class="small fw-bold text-body">Öğr. Gör. Samet ATABAŞ</span>
                    </div>
                    <div class="text-muted small" style="font-size: 0.75rem;">
                        <span>Sürüm</span> <span class="fw-semibold text-secondary">v<?= \App\Helpers\getAppVersion() ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
<!--end::Institutional Footer-->