<?php use function App\Helpers\getAppVersion; ?>
<footer class="app-footer">
    <div class="float-end d-none d-sm-inline">
        <a href="/legal/kvkk" target="_blank" class="text-secondary text-decoration-none me-3 hover-primary">
            <i class="bi bi-shield-lock me-1"></i>KVKK Aydınlatma Metni
        </a>
        <a href="/legal/privacy" target="_blank" class="text-secondary text-decoration-none me-3 hover-primary">
            <i class="bi bi-shield-check me-1"></i>Gizlilik & Çerezler
        </a>
        <b>Version</b>
        <?= getAppVersion() ?>
    </div>
    <!--begin::Copyright-->
    <strong>
        Ders ve Sınav Programı Bilgi Sistemi &copy; <?= date('Y') ?>&nbsp;
    </strong>
    <!--end::Copyright-->
</footer>