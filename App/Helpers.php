<?php

namespace App\Helpers;

use App\Controllers\SettingsController;
use Exception;

function getSetting($key = null, $group = "general")
{
    try {
        $settingsController = new SettingsController();
        $setting = $settingsController->getSetting($key,$group);
        return match ($setting->type) {
            'integer' => (int)$setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            default => $setting->value
        };
    } catch (Exception $e) {
        $_SESSION['errors'][] = "getSetting hatası";
        $_SESSION['errors'][] = $e->getMessage();
        return false;
    }
}