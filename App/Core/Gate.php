<?php

namespace App\Core;

use App\Middlewares\AuthMiddleware;
use Exception;
use App\Core\Log;
use function App\Helpers\getSettingValue;

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
    public static function check(string $action, $model): bool
    {
        $user = AuthMiddleware::user();

        $modelClass = is_object($model) ? get_class($model) : $model;
        $policyClass = self::$policies[$modelClass] ?? null;

        if (!$policyClass) {
            return false;
        }

        if (!class_exists($policyClass)) {
            throw new Exception("Politika sınıfı bulunamadı: $policyClass");
        }

        $policy = new $policyClass();

        if (!method_exists($policy, $action)) {
            return false;
        }

        // Reflection ile metodun misafir kullanıcı (null) kabul edip etmediğini kontrol ediyoruz
        $reflectionMethod = new \ReflectionMethod($policy, $action);
        $parameters = $reflectionMethod->getParameters();

        if (empty($parameters)) {
            if (!$user) {
                return false;
            }
        } else {
            $userParam = $parameters[0];
            // Eğer kullanıcı oturum açmamışsa ve metod null kabul etmiyorsa yetki verilmez
            if (!$user && !$userParam->allowsNull()) {
                return false;
            }
        }

        // 'before' kontrolü
        if (method_exists($policy, 'before')) {
            $before = $policy->before($user, $action);
            if ($before !== null) {
                return $before;
            }
        }

        // Aksiyona özel metot çağrısı
        return $policy->$action($user, $model);
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
            throw new Exception($message);
        }
    }

    /**
     * Kullanıcının özel yetkilerini Settings'ten JSON olarak alır
     * @param int|null $userId
     * @return array
     */
    public static function getUserPermissions(?int $userId = null): array
    {
        if (is_null($userId)) {
            $user = AuthMiddleware::user();
            if (!$user) return [];
            $userId = $user->id;
        }

        $key = 'user_' . $userId . '_permissions';
        $perms = getSettingValue($key, 'user_permissions', []);
        return is_array($perms) ? $perms : [];
    }
}
