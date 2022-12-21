<?php
require "../vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__."/../App");
$dotenv->load();

use App\Core\Application;

new Application();
