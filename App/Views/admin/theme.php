<!DOCTYPE html>
<!--<html lang="<?php /*= $currentLanguage ?? 'tr' */ ?>"> todo bunu düzenleyip tüm theme sayfalarına eklemek lazım -->
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
    try {
        $currentUser = $userController->getCurrentUser();
    } catch (Exception $e) {
        $_SESSION["errors"][] = $e->getMessage();
    }
    include "theme/navbar.php";
    include "theme/sidebar.php";
    include "pages/" . $this->view_page . ".php";
    include "theme/footer.php" ?>

</div>
<!--end::App Wrapper-->

<?php
include "theme/footer_scripts.php";
if (isset($_SESSION['errors'])) {
    foreach ($_SESSION['errors'] as $errorKey => $errorValue) {
        /**
         * Tüm hata mesajları throw edilmeden önce dizi şeklinde session a yada başka bir değişkene kaydedilerek konsolda gösterilebilir
         * Şuanki hali ile Session verisi sayfa yenilendikten sonra gözüküyor. Bir ajax sorgusu ile Session değerlerini sayfa yenilemeden alabilirim
         * Bu sayede hata mesajlarının ne zaman nerede hangi sırayla oluştuğunu da görebilirm
         * Log kaydı eklediğimde bu işlem loglarda tutulacak aslında.
         * @see \App\Controllers\ScheduleController:608
         */
        if (is_array($errorValue)) {
            foreach ($errorValue as $error) {
                echo '<script>
            document.addEventListener("DOMContentLoaded", function () {
            console.log("' . $errorKey . ' => ' . $error . '");
            });
            </script>';
            }

        } else {
            echo '<script>
            document.addEventListener("DOMContentLoaded", function () {
            alert("' . $errorValue . '");
            });
            </script>';
        }
    }
    unset($_SESSION['errors']);
}
?>
<!-- todo buraya eğer sessionda hata tenımlanmışsa toast ile hataları göstermek için bir şeyler eklenmeli -->
</body>
</html>
