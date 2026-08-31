<!DOCTYPE html>
<html lang="tr" data-bs-theme="<?php echo $_COOKIE['theme'] ?? 'light'; ?>">
<?php
include "theme/head.php";
?>
<!--begin::Body-->
<body class="d-flex flex-column min-vh-100 bg-body-tertiary font-sans" data-overlayscrollbars-initialize>
    <!--begin::App Wrapper-->
    <div class="app-public-wrapper d-flex flex-column min-vh-100 w-100">
        <?php
        include "theme/navbar.php";
        ?>

        <!--begin::Main Content Area-->
        <div class="app-public-main flex-grow-1">
            <?php include $filePath; ?>
        </div>
        <!--end::Main Content Area-->

        <?php
        include "theme/footer.php";
        ?>
    </div>
    <!--end::App Wrapper-->

    <!--begin::Quick Help Modal-->
    <div class="modal fade" id="quickHelpModal" tabindex="-1" aria-labelledby="quickHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="bi bi-info-circle-fill fs-5"></i>
                        </div>
                        <h5 class="modal-title fw-bold" id="quickHelpModalLabel">Kullanım Rehberi & İpuçları</h5>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex gap-3">
                            <div class="text-primary fs-4"><i class="bi bi-mortarboard"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1">1. Program Türünü ve Dönemi Seçin</h6>
                                <p class="text-muted small mb-0">Haftalık ders programı veya Ara Sınav / Final / Bütünleme sınav takvimini belirleyin.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="text-success fs-4"><i class="bi bi-filter-circle"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1">2. Birim ve Programınızı Bulun</h6>
                                <p class="text-muted small mb-0">Fakülte/MYO, Bölüm ve Programınızı sırayla seçerek <strong>Göster</strong> butonuna tıklayın.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="text-warning fs-4"><i class="bi bi-calendar-plus"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1">3. Takviminize Ekleyin veya İndirin</h6>
                                <p class="text-muted small mb-0">Program tablosunun sağ üst köşesindeki <strong>Takvime Kaydet</strong> (iCal/ICS) ile telefon takviminize aktarabilir veya <strong>Excel</strong> olarak indirebilirsiniz.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-primary w-100 rounded-pill" data-bs-dismiss="modal">Anladım, Kapat</button>
                </div>
            </div>
        </div>
    </div>
    <!--end::Quick Help Modal-->

    <?php
    include "theme/footer_scripts.php";
    if (isset($_SESSION['error'])) {
        echo '<script>
        document.addEventListener("DOMContentLoaded", function () {
            if (typeof Toast !== "undefined") {
                new Toast().prepareToast("Hata", "' . addslashes($_SESSION['error']) . '", "danger");
            }
        });
        </script>';
        unset($_SESSION['error']);
    }
    ?>
</body>
</html>