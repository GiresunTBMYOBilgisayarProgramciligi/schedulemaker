<?php
require "../vendor/autoload.php";



use App\Core\Application;
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
new Application();
