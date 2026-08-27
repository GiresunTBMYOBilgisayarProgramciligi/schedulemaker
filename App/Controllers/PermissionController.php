<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Setting;
use App\Core\Gate;
use App\Validators\PermissionValidator;
use Exception;

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
            "status"      => "success",
            "permissions" => $permissions
        ];
    }

    /**
     * @param array $requestData
     * @return array
     * @throws Exception
     */
    public function savePermissions(array $requestData): array
    {
        Gate::authorizeRole("submanager", false, "Yetki düzenleme yetkiniz yok");

        $dto = (new PermissionValidator())->getDTO($requestData);

        $settingModel = new Setting();
        $setting = $settingModel->get()->where(["key" => "user_{$dto->userId}", "group" => "permissions"])->first();

        $currentPermissions = [];
        if ($setting) {
            $currentPermissions = json_decode($setting->value, true) ?: [];
        }

        // Initialize scope array if it doesn't exist
        if (!isset($currentPermissions[$dto->scope])) {
            $currentPermissions[$dto->scope] = [];
        }

        // Update permissions for the target
        if (empty($dto->permissions)) {
            if (isset($currentPermissions[$dto->scope][$dto->targetId])) {
                unset($currentPermissions[$dto->scope][$dto->targetId]);
            }
        } else {
            $currentPermissions[$dto->scope][$dto->targetId] = $dto->permissions;
        }

        $encodedPermissions = json_encode($currentPermissions);

        if ($setting) {
            $setting->value = $encodedPermissions;
            $setting->type = 'json';
            $setting->update();
        } else {
            $settingModel->group = 'permissions';
            $settingModel->key = "user_{$dto->userId}";
            $settingModel->value = $encodedPermissions;
            $settingModel->type = 'json';
            $settingModel->create();
        }

        return [
            "status" => "success",
            "msg"    => "Yetkiler başarıyla güncellendi"
        ];
    }
}
