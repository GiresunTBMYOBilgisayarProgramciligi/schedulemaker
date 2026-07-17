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

        $key = 'user_' . $userId;
        $perms = getSettingValue($key, 'permissions', []);
        
        // Geriye dönük uyumluluk veya yanlış kayıtlara karşı:
        // Eğer json direkt dizi ise
        if (!is_array($perms)) {
            $perms = [];
        }
        
        return $perms;
    }

    /**
     * Kullanıcının kaskad hiyerarşisinde (Birim -> Bölüm -> Program) ilgili yetkiye sahip olup olmadığını kontrol eder.
     * Eğer MANAGE_BUILDINGS gibi global bir yetki ise, JSON içerisinde herhangi bir yerde geçip geçmediğine bakar.
     *
     * @param int $userId
     * @param string $permission
     * @param mixed $model Model nesnesi (Unit, Department, Program, Lesson vb.) veya null
     * @param array $data Opsiyonel id verileri
     * @return bool
     */
    public static function hasCascadePermission(int $userId, string $permission, $model = null, array $data = []): bool
    {
        $perms = self::getUserPermissions($userId);

        // Global yetkiler (örn. MANAGE_BUILDINGS) tüm sistemde geçerlidir
        if (in_array($permission, [\App\Enums\PermissionType::MANAGE_BUILDINGS->value])) {
            foreach ($perms as $scope => $items) {
                foreach ($items as $id => $grantedPerms) {
                    if (is_array($grantedPerms) && in_array($permission, $grantedPerms)) {
                        return true;
                    }
                }
            }
            return false;
        }

        $unitId = $data['unit_id'] ?? null;
        $departmentId = $data['department_id'] ?? null;
        $programId = $data['program_id'] ?? null;

        if ($model) {
            $programId = $programId ?? ($model->program_id ?? ($model instanceof \App\Models\Program ? $model->id : null));
            $departmentId = $departmentId ?? ($model->department_id ?? ($model instanceof \App\Models\Department ? $model->id : null));
            $unitId = $unitId ?? ($model->unit_id ?? ($model instanceof \App\Models\Unit ? $model->id : null));
        }

        if ($programId && !$departmentId) {
            $prog = (new \App\Models\Program())->find($programId);
            $departmentId = $prog->department_id ?? null;
        }
        if ($departmentId && !$unitId) {
            $dept = (new \App\Models\Department())->find($departmentId);
            $unitId = $dept->unit_id ?? null;
        }

        // 1. Program Seviyesi
        if ($programId && in_array($permission, $perms['programs'][$programId] ?? [])) {
            return true;
        }
        // 2. Bölüm Seviyesi
        if ($departmentId && in_array($permission, $perms['departments'][$departmentId] ?? [])) {
            return true;
        }
        // 3. Birim Seviyesi
        if ($unitId && in_array($permission, $perms['units'][$unitId] ?? [])) {
            return true;
        }

        return false;
    }
}
