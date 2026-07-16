<?php
/**
 * @var \App\Core\AssetManager $assetManager
 * @var string $page_title
 */
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=$page_title?> - TMYO Ders Programı</title>

    <?= $assetManager->renderCss() ?>
    <script>
        // AdminLTE 4'ün işletim sistemine göre otomatik dark temaya geçmesini engellemek
        // ve PHP'deki cookie değerini (varsayılan: light) baz almasını sağlamak için:
        localStorage.setItem('lte-theme', '<?php echo $_COOKIE['theme'] ?? 'light'; ?>');

        // Kullanıcı menüden temayı değiştirdiğinde bunu cookie'ye kaydet:
        document.addEventListener('changed.lte.color-mode', function(e) {
            document.cookie = "theme=" + e.detail.theme + "; path=/; max-age=" + (60*60*24*365);
        });
    </script>
</head>