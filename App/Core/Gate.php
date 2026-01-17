<?php

namespace App\Core;

use App\Controllers\UserController;
use App\Models\User;
use Exception;

/**
 * Yetki kontrolü için merkezi yönetim sınıfı
 */
class Gate
{
    /**
     * @var array Model-Policy eşleşmeleri
     */
    private static array $policies = [
        'App\Models\User' => 'App\Policies\UserPolicy',
        'App\Models\Lesson' => 'App\Policies\LessonPolicy',
        'App\Models\Program' => 'App\Policies\ProgramPolicy',
        'App\Models\Department' => 'App\Policies\DepartmentPolicy',
        'App\Models\Schedule' => 'App\Policies\SchedulePolicy',
    ];

    /**
     * Belirtilen model için yetki kontrolü yapar
     * 
     * @param string $action 'view', 'create', 'update', 'delete' vb.
     * @param mixed $model Model nesnesi veya sınıf adı (create için sınıf adı olabilir)
     * @return bool
     * @throws Exception
     */
    public static function check(string $action, $model): bool
    {
        $user = (new UserController())->getCurrentUser();
        if (!$user) {
            return false;
        }

        $modelClass = is_object($model) ? get_class($model) : $model;
        $policyClass = self::$policies[$modelClass] ?? null;

        if (!$policyClass) {
            // Eğer politika bulunamazsa eski sisteme pasla veya varsayılan kural uygula
            return false;
        }

        if (!class_exists($policyClass)) {
            throw new Exception("Politika sınıfı bulunamadı: $policyClass");
        }

        $policy = new $policyClass();

        // 'before' kontrolü
        if (method_exists($policy, 'before')) {
            $before = $policy->before($user, $action);
            if ($before !== null) {
                return $before;
            }
        }

        // Aksiyona özel metot kontrolü (örn. view, update, delete)
        if (method_exists($policy, $action)) {
            return $policy->$action($user, $model);
        }

        // Metot bulunamazsa varsayılan olarak reddet
        return false;
    }
}
