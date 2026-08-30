<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Mailer;

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

// Projenin App/.env dosyasını test ortamında yükle
if (file_exists(__DIR__ . '/../App/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../App');
    $dotenv->safeLoad();
}

$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

// Test ortamında e-postaların gerçek SMTP'ye gitmesini kesin olarak engelle
Mailer::fake();

// Tüm olay (event) ve dinleyici (listener) kayıtlarını yükle
$eventsConfig = __DIR__ . '/../App/config/events.php';
if (file_exists($eventsConfig)) {
    require_once $eventsConfig;
}

