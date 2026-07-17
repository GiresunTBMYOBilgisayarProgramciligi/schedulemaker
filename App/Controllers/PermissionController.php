<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Setting;
use App\Core\Gate;

class PermissionController extends Controller
{
    /**
     * @param int $userId
     * @return array
     */
    public function getUserPermissions(int $userId): array
    {
        Gate::authorizeRole("submanager", false, "Yetkileri görüntüleme yetkiniz yok");

        $settingModel = new Setting();
        $setting = $settingModel->get()->where(["key" => "user_{$userId}", "group" => "permissions"])->first();

        $permissions = [];
        if ($setting) {
            $permissions = json_decode($setting->value, true) ?: [];
        }

        return [
            "status" => "success",
            "permissions" => $permissions
        ];
    }

    /**
     * @param array $requestData
     * @return array
     */
    public function savePermissions(array $requestData): array
    {
        Gate::authorizeRole("submanager", false, "Yetki düzenleme yetkiniz yok");

        $userId = $requestData['user_id'] ?? null;
        $scope = $requestData['scope'] ?? null; // 'units', 'departments', 'programs'
        $targetId = $requestData['target_id'] ?? null;
        $permissionsJson = $requestData['permissions'] ?? '[]';

        if (!$userId || !$scope || !$targetId) {
            return [
                "status" => "error",
                "msg" => "Eksik parametreler"
            ];
        }

        $newPermissions = json_decode($permissionsJson, true);
        if (!is_array($newPermissions)) {
            $newPermissions = [];
        }

        $settingModel = new Setting();
        $setting = $settingModel->get()->where(["key" => "user_{$userId}", "group" => "permissions"])->first();

        $currentPermissions = [];
        if ($setting) {
            $currentPermissions = json_decode($setting->value, true) ?: [];
        }

        // Initialize scope array if it doesn't exist
        if (!isset($currentPermissions[$scope])) {
            $currentPermissions[$scope] = [];
        }

        // Update permissions for the target
        if (empty($newPermissions)) {
            // Remove target id if permissions are empty
            if (isset($currentPermissions[$scope][$targetId])) {
                unset($currentPermissions[$scope][$targetId]);
            }
        } else {
            // Replace with new permissions
            $currentPermissions[$scope][$targetId] = $newPermissions;
        }

        $encodedPermissions = json_encode($currentPermissions);

        if ($setting) {
            // Update existing
            $setting->update([
                'value' => $encodedPermissions,
                'type' => 'json'
            ]);
        } else {
            // Create new
            $settingModel->insert([
                'group' => 'permissions',
                'key' => "user_{$userId}",
                'value' => $encodedPermissions,
                'type' => 'json'
            ]);
        }

        return [
            "status" => "success",
            "msg" => "Yetkiler başarıyla güncellendi"
        ];
    }
}
