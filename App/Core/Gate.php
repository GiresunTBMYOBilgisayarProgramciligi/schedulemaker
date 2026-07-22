<?php

namespace App\Core;

use App\Middlewares\AuthMiddleware;
use Exception;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Policies\BasePolicy;

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
        'App\Models\Classroom' => 'App\Policies\ClassroomPolicy',
        'App\Models\Unit' => 'App\Policies\UnitPolicy',
        'App\Models\Building' => 'App\Policies\BuildingPolicy',
        'App\Models\Setting' => 'App\Policies\SettingPolicy',
    ];

    /**
     * @var array Rol hiyerarşisi (Sayısal seviyeler)
     */
    private static array $roleLevels = [
        "admin" => 100,
        "manager" => 90,
        "submanager" => 80,
        "secretary" => 75,
        "department_head" => 70,
        "research_assistant" => 65,
        "lecturer" => 60,
        "user" => 50
    ];

    /**
     * Belirtilen model için yetki kontrolü yapar
     * 
     * @param string $action 'view', 'create', 'update', 'delete', 'list' vb.
     * @param mixed $model Model nesnesi veya sınıf adı
     * @return bool
     * @throws Exception
     */
    public static function check(string $action, $model, $dto = null): bool
    {
        $user = AuthMiddleware::user();

        $modelClass = is_object($model) ? get_class($model) : $model;
        $policyClass = self::$policies[$modelClass] ?? null;

        if (!$policyClass || !class_exists($policyClass)) {
            return false;
        }

        /** @var \App\Policies\BasePolicy $policy */
        $policy = new $policyClass();

        // 1. 'before' kontrolü (admin gibi global yetkiler için)
        if (method_exists($policy, 'before')) {
            $before = $policy->before($user, $action);
            if ($before !== null) {
                return $before;
            }
        }

        // 2. Misafir kullanıcı kontrolü (Oturum açmamışsa reddet)
        if (!$user) {
            $allowGuest = false;
            if (method_exists($policy, $action)) {
                try {
                    $refMethod = new \ReflectionMethod($policy, $action);
                    $params = $refMethod->getParameters();
                    if (count($params) > 0 && $params[0]->allowsNull()) {
                        $allowGuest = true;
                    }
                } catch (\ReflectionException $e) {
                    // Ignore
                }
            }
            if (!$allowGuest) {
                return false;
            }
        }

        // 3. Yetki kararını tamamen ilgili Politika sınıfına devret (Metod varsa çalışır, yoksa BasePolicy::__call devreye girer)
        if (method_exists($policy, $action) || is_callable([$policy, $action]) || str_starts_with($action, 'manage_')) {
            return $policy->$action($user, $model, $dto);
        }

        return false;
    }

    /**
     * Belirtilen yetkiyi kontrol eder, yetkisiz ise Exception fırlatır.
     * 
     * @param string $action
     * @param mixed $model
     * @param string $message
     * @param mixed $dto
     * @throws AuthorizationException
     */
    public static function authorize(string $action, $model, string $message = "Bu işlem için yetkiniz bulunmamaktadır.", $dto = null): void
    {
        if (!self::check($action, $model, $dto)) {
            throw new AuthorizationException($message);
        }
    }

    /**
     * Sadece rol bazlı yetkisini kontrol eder.
     * 
     * @param string $role Gereken minimum rol (örn. 'submanager')
     * @param bool $reverse true ise belirtilen rolden daha düşük roller izin alır
     * @return bool
     */
    public static function allowsRole(string $role, bool $reverse = false): bool
    {
        $user = AuthMiddleware::user();
        if (!$user) {
            return false;
        }

        $requiredLevel = self::$roleLevels[$role] ?? 0;
        $userLevel = self::$roleLevels[$user->role] ?? 50;

        if ($reverse) {
            return $userLevel <= $requiredLevel;
        }

        return $userLevel >= $requiredLevel;
    }

    /**
     * Rolü kontrol eder, yoksa Exception fırlatır.
     * 
     * @param string $role
     * @param bool $reverse
     * @param string $message
     * @throws Exception
     */
    public static function authorizeRole(string $role, bool $reverse = false, string $message = "Bu sayfayı görüntülemek için yetkiniz yok."): void
    {
        if (!self::allowsRole($role, $reverse)) {
            throw new AuthorizationException($message);
        }
    }

    /**
     * Kullanıcının herhangi bir yerde ilgili yetkiye sahip olup olmadığını kontrol eder (Menü görünürlüğü vb. için)
     */
    public static function hasAnyPermission(User|int $user, string $permission): bool
    {
        return (new class extends BasePolicy {})->hasAnyPermission($user, $permission);
    }
}
