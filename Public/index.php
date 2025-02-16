<?php
require "../vendor/autoload.php";
use Dotenv\Dotenv;
use App\Core\Application;
$dotenv = Dotenv::createImmutable(__DIR__."/../App");
$dotenv->load();

ini_set('display_errors', 1);
error_reporting(E_ERROR);
session_start();
new Application();
