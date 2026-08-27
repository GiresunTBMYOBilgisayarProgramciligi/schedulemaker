<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Projenin App/.env dosyasını test ortamında yükle
if (file_exists(__DIR__ . '/../App/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../App');
    $dotenv->safeLoad();
}
