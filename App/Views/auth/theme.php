<!DOCTYPE html>
<?php
$themeMode = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'dark' : 'light';
?>
<html lang="tr" data-bs-theme="<?= $themeMode ?>">
<?php
include "theme/head.php";
include $filePath;
include "theme/footer_scripts.php";
?>
</html>