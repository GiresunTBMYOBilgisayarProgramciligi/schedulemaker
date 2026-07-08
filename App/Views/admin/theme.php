<!DOCTYPE html>
<html lang="tr" >
<?php
include "theme/head.php";
?>
<!--begin::Body-->
<!--todo theme atarı profil sayfasından yapılmalı ve cookie olarak tanımlanmalı-->

<body class="layout-fixed sidebar-expand-lg sidebar-mini sidebar-collapse bg-body-tertiary app-loaded" data-bs-theme="<?php echo $_COOKIE['theme'] ?? 'light'; ?>">
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