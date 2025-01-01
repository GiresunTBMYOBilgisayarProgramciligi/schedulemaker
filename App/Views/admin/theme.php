<!DOCTYPE html>
<!--<html lang="<?php /*= $currentLanguage ?? 'tr' */?>"> todo bunu düzenleyip tüm theme sayfalarına eklemek lazım -->
<html lang="tr">
<?php
include "theme/head.php";
?>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php
    /** @var \App\Models\UsersController $usersController */
    $currentUser = $usersController->getCurrentUser();
    include "theme/navbar.php";
    include "theme/sidebar.php";
    include "pages/" . $this->view_page . ".php";
    include "theme/footer.php" ?>

</div>
<!-- ./wrapper -->

<?php
include "theme/footer_scripts.php";
?>
</body>
</html>
