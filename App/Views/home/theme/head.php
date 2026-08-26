<?php
/**
 * @var \App\Core\AssetManager $assetManager
 * @var string $page_title
 */
$fullTitle = ($page_title ?? 'Anasayfa') . ' | Giresun Üniversitesi Ders ve Sınav Programı Bilgi Sistemi';
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Giresun Üniversitesi tüm fakülte, yüksekokul ve meslek yüksekokulları haftalık ders ve sınav programı yönetim ve görüntüleme sistemi.">
    <meta name="author" content="Öğr. Gör. Samet ATABAŞ">
    <title><?= htmlspecialchars($fullTitle) ?></title>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?= $assetManager->renderCss() ?>
    <script>
        // AdminLTE 4 dark/light tema yönetimi:
        localStorage.setItem('lte-theme', '<?php echo $_COOKIE['theme'] ?? 'light'; ?>');

        // Kullanıcı temayı değiştirdiğinde cookie'ye kaydet:
        document.addEventListener('changed.lte.color-mode', function(e) {
            document.cookie = "theme=" + e.detail.theme + "; path=/; max-age=" + (60*60*24*365);
        });
    </script>
</head>