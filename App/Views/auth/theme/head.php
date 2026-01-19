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
</head>