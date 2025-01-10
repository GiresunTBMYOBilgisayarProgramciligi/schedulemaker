<!DOCTYPE html>
<!--<html lang="<?php /*= $currentLanguage ?? 'tr' */?>"> todo bunu düzenleyip tüm theme sayfalarına eklemek lazım -->
<html lang="tr">
<?php
include "theme/head.php";
?>
<!--begin::Body-->
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary">
<!--begin::App Wrapper-->
<div class="app-wrapper">

    <?php
    /** @var \App\Controllers\UserController $userController
     * @var \App\Models\User | false $currentUser Oturum açmış kullanıcı modeli. Oturum açılmamışsa false
     * */
    $currentUser = $userController->getCurrentUser();
    include "theme/navbar.php";
    include "theme/sidebar.php";
    include "pages/" . $this->view_page . ".php";
    include "theme/footer.php" ?>

</div>
<!--end::App Wrapper-->

<?php
include "theme/footer_scripts.php";
?>
</body>
</html>
