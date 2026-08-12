<!DOCTYPE html>
<html lang="tr" data-bs-theme="<?php echo $_COOKIE['theme'] ?? 'light'; ?>">
<?php
include "theme/head.php";
?>
<!--begin::Body-->

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary sidebar-mini sidebar-collapse" data-overlayscrollbars-initialize>
    <!--begin::App Wrapper-->
    <div class="app-wrapper">

        <?php
        include "theme/navbar.php";
        include "theme/sidebar.php";
        include $filePath;
        include "theme/footer.php" ?>

    </div>
    <!--end::App Wrapper-->

    <?php
    include "theme/footer_scripts.php";
    if (isset($_SESSION['error'])) {
        echo '<script>
    document.addEventListener("DOMContentLoaded", function () {
            new Toast().prepareToast("Hata","' . $_SESSION['error'] . '","danger");
    });
    </script>';
        unset($_SESSION['error']);
    }
    ?>
</body>

</html>