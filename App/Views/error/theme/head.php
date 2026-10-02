<?php
/**
 * @var \App\Core\AssetManager $assetManager
 * @var string $page_title
 */
use function App\Helpers\csrf_token;
use function App\Helpers\e;
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($page_title) ?> - TMYO Ders Programı</title>

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