<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

// Projenin App/.env dosyasını test ortamında yükle
if (file_exists(__DIR__ . '/../App/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../App');
    $dotenv->safeLoad();
}

$_ENV['APP_ENV'] = 'testing';

