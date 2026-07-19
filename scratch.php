<?php
require 'vendor/autoload.php';
$pdo = \App\Core\Database::getInstance()->getConnection();
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='user_180'");
print_r($stmt->fetchColumn());
