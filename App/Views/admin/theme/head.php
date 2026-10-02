<?php
/**
 * @var AssetManager $assetManager
 * @var string       $page_title
 */

use App\Core\AssetManager;
use function App\Helpers\e;

$themeMode = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'dark' : 'light';
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> - TMYO Ders Programı</title>

    <?= $assetManager->renderCss() ?>
    <script>
        // AdminLTE 4'ün işletim sistemine göre otomatik dark temaya geçmesini engellemek
        // ve PHP'deki cookie değerini (varsayılan: light) baz almasını sağlamak için:
        localStorage.setItem('lte-theme', '<?= $themeMode ?>');

        // Kullanıcı menüden temayı değiştirdiğinde bunu cookie'ye kaydet:
        document.addEventListener('changed.lte.color-mode', function(e) {
            document.cookie = "theme=" + e.detail.theme + "; path=/; max-age=" + (60*60*24*365);
        });
    </script>
</head>