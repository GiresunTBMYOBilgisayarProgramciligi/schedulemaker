<?php

namespace App\Core;

use App\Controllers\UserController;
use Exception;
use App\Core\Log;

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
    ];

    /**
     * @var array Rol hiyerarşisi (Sayısal seviyeler)
     */
    private static array $roleLevels = [
        "admin" => 10,
        "manager" => 9,
        "submanager" => 8,
        "department_head" => 7,
        "lecturer" => 6,
        "user" => 5
    ];

    /**
     * Belirtilen model için yetki kontrolü yapar
     * 
     * @param string $action 'view', 'create', 'update', 'delete', 'list' vb.
     * @param mixed $model Model nesnesi veya sınıf adı
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

        return false;
    }

    /**
     * Yetkiyi kontrol eder, yoksa Exception fırlatır.
     * 
     * @param string $action
     * @param mixed $model
     * @param string $message Hata mesajı
     * @throws Exception
     */
    public static function authorize(string $action, $model, string $message = "Bu işlem için yetkiniz bulunmamaktadır."): void
    {
        if (!self::check($action, $model)) {
            throw new Exception($message);
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
        $user = (new UserController())->getCurrentUser();
        Log::logger()->debug("user: " . var_export($user->getArray(), true));
        if (!$user) {
            return false;
        }

        $requiredLevel = self::$roleLevels[$role] ?? 0;
        $userLevel = self::$roleLevels[$user->role] ?? 5;

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
            throw new Exception($message);
        }
    }
}
