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

    <script>
        const storedTheme = localStorage.getItem('theme');
        const cookieTheme = document.cookie.split('; ').find(row => row.startsWith('theme='));

        if (storedTheme) {
            document.documentElement.setAttribute('data-bs-theme', storedTheme);
            if (!cookieTheme || cookieTheme.split('=')[1] !== storedTheme) {
                document.cookie = `theme=${storedTheme}; path=/; max-age=31536000`;
            }
        }
    </script>

    <?= $assetManager->renderCss() ?>
</head>