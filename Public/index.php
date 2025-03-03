<?php
require "../vendor/autoload.php";

use Dotenv\Dotenv;
use App\Core\Application;

$dotenv = Dotenv::createImmutable(__DIR__ . "/../App");
$dotenv->load();
define("DEBUG_MODE", $_ENV['DEBUG']); // Geliştirme ortamında true, canlı ortamda false

ini_set('display_errors', 1);
error_reporting(E_ERROR);
// Hata işleyicisini başlat
$errorHandler = new \App\Core\ErrorHandler();
$errorHandler->register();

session_start();
new Application();
