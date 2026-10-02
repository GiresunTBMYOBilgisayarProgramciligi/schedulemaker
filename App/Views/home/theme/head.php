<?php
/**
 * @var \App\Core\AssetManager $assetManager
 * @var string $page_title
 */
use function App\Helpers\csrf_token;
use function App\Helpers\e;

$fullTitle = ($page_title ?? 'Anasayfa') . ' | Giresun Üniversitesi Ders ve Sınav Programı Bilgi Sistemi';
$themeMode = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'dark' : 'light';
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="description" content="Giresun Üniversitesi tüm fakülte, yüksekokul ve meslek yüksekokulları haftalık ders ve sınav programı yönetim ve görüntüleme sistemi.">
    <meta name="author" content="Öğr. Gör. Samet ATABAŞ">
    <title><?= e($fullTitle) ?></title>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?= $assetManager->renderCss() ?>
    <script>
        // AdminLTE 4 dark/light tema yönetimi:
        localStorage.setItem('lte-theme', '<?= $themeMode ?>');

        // Kullanıcı temayı değiştirdiğinde cookie'ye kaydet:
        document.addEventListener('changed.lte.color-mode', function(e) {
            document.cookie = "theme=" + e.detail.theme + "; path=/; max-age=" + (60*60*24*365);
        });
    </script>
</head>