<!DOCTYPE html>
<html lang="tr" data-bs-theme="<?php echo htmlspecialchars($_COOKIE['theme'] ?? 'light', ENT_QUOTES, 'UTF-8'); ?>">
<?php
include "theme/head.php";
?>
<!--begin::Body-->
<!--todo theme atarı profil sayfasından yapılmalı ve cookie olarak tanımlanmalı-->
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary sidebar-mini sidebar-collapse" data-overlayscrollbars-initialize>
    <!--begin::App Wrapper-->
    <div class="app-wrapper">

        <?php
        include "theme/navbar.php";
        include "theme/sidebar.php";
        include $filePath;
        include "theme/footer.php";
        include "theme/consent_modal.php";
        ?>

    </div>
    <!--end::App Wrapper-->

    <?php
    include "theme/footer_scripts.php";
    if (isset($_SESSION['error'])) {
        $errorMsg = json_encode((string)$_SESSION['error']);
        echo '<script>
    document.addEventListener("DOMContentLoaded", function () {
            new Toast().prepareToast("Hata",' . $errorMsg . ',"danger");
    });
    </script>';
        unset($_SESSION['error']);
    }
    ?>
</body>

</html>