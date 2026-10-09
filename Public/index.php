<?php
require "../vendor/autoload.php";

use Dotenv\Dotenv;
use App\Core\Application;

$dotenv = Dotenv::createImmutable(__DIR__ . "/../App");
$dotenv->load();
define("DEBUG_MODE", filter_var($_ENV['DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)); // Geliştirme ortamında true, canlı ortamda false

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}
date_default_timezone_set('Europe/Istanbul');
setlocale(LC_ALL, 'tr_TR.UTF-8');

// Hata işleyicisini başlat
$errorHandler = new \App\Core\ErrorHandler();
$errorHandler->register();

// Oturum ayarları: Uzun süreli planlama ve düzenlemelerde oturumun erken düşmesini engelle (4 saat)
ini_set('session.gc_maxlifetime', '14400');
session_set_cookie_params([
    'lifetime' => 14400,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
new Application();
