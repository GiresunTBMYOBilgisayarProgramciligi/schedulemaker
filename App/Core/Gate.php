<?php

namespace App\Core;

use App\Middlewares\AuthMiddleware;
use Exception;
use App\Exceptions\AuthorizationException;
use App\Core\Log;
use App\Enums\PermissionType;
use App\Models\Unit;
use App\Models\Department;
use App\Models\Program;
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
     public static function check(string $action, $model, $dto = null): bool
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
            // Eğer action 'manage_' ile başlıyorsa özel yetki (cascade) kontrolü yap
            if (str_starts_with($action, 'manage_')) {
                // 'before' kontrolü (manager/submanager gibi global yetkiler için)
                if (method_exists($policy, 'before')) {
                    $before = $policy->before($user, $action);
                    if ($before !== null) {
                        return $before;
                    }
                }



                // department_head yetkisi sadece kendi bölümü ve alt programları için (manage_schedule)
                if ($user->role === 'department_head' && $action === PermissionType::MANAGE_SCHEDULE->value) {
                    if ($model instanceof Department && $model->id === $user->department_id) {
                        return true;
                    }
                    if ($model instanceof Program && $model->department_id === $user->department_id) {
                        return true;
                    }
                }

                return self::hasCascadePermission($user->id, $action, $model);
            }
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
        if ($action === 'create' && $dto !== null) {
            return $policy->$action($user, $dto);
        }
        return $policy->$action($user, $model, $dto);
    }

    /**
     * Yetkiyi kontrol eder, yoksa Exception fırlatır.
     * 
     * @param string $action
     * @param mixed $model
     * @param string $message Hata mesajı
     * @throws Exception
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

        $unitId = $data['unit_id'] ?? null;
        $departmentId = $data['department_id'] ?? null;
        $programId = $data['program_id'] ?? null;

        if ($model) {
            $programId = $programId ?? ($model->program_id ?? ($model instanceof Program ? $model->id : null));
            $departmentId = $departmentId ?? ($model->department_id ?? ($model instanceof Department ? $model->id : null));
            $unitId = $unitId ?? ($model->unit_id ?? ($model instanceof Unit ? $model->id : null));
        }

        if ($programId && !$departmentId) {
            $prog = (new Program())->find($programId);
            $departmentId = $prog->department_id ?? null;
        }
        if ($departmentId && !$unitId) {
            $dept = (new Department())->find($departmentId);
            $unitId = $dept->unit_id ?? null;
        }

        // Eğer hiçbir spesifik hedef belirtilmemişse (örn. liste sayfaları için),
        // yetkinin herhangi bir yerde tanımlı olup olmadığına bak.
        if (!$programId && !$departmentId && !$unitId) {
            return self::hasAnyPermission($userId, $permission);
        }

        $impliedPermissions = [$permission];
        $manageProgramImplies = [PermissionType::MANAGE_PROGRAM->value, PermissionType::MANAGE_USERS->value, PermissionType::MANAGE_SCHEDULE->value, PermissionType::MANAGE_LESSONS->value];
        if (in_array($permission, $manageProgramImplies)) {
            $impliedPermissions[] = PermissionType::MANAGE_DEPARTMENT->value;
            $impliedPermissions[] = PermissionType::MANAGE_UNIT->value;
        }
        if ($permission === PermissionType::MANAGE_DEPARTMENT->value) {
            $impliedPermissions[] = PermissionType::MANAGE_UNIT->value;
        }

        $hasPerm = function($haystack) use ($impliedPermissions) {
            return is_array($haystack) && count(array_intersect($haystack, $impliedPermissions)) > 0;
        };

        // 1. Program Seviyesi
        if ($programId && $hasPerm($perms['programs'][$programId] ?? [])) {
            return true;
        }
        // 2. Bölüm Seviyesi
        if ($departmentId && $hasPerm($perms['departments'][$departmentId] ?? [])) {
            return true;
        }
        // 3. Birim Seviyesi
        if ($unitId && $hasPerm($perms['units'][$unitId] ?? [])) {
            return true;
        }

        // --- AŞAĞI YÖNLÜ (KASKAD) KONTROL (Çocuklarda yetki var mı?) ---
        if ($model) {
            if ($model instanceof Unit) {
                $departments = (new Department())->get()->where(['unit_id' => $model->id])->all();
                foreach ($departments as $dept) {
                    if ($hasPerm($perms['departments'][$dept->id] ?? [])) return true;
                    $programs = (new Program())->get()->where(['department_id' => $dept->id])->all();
                    foreach ($programs as $prog) {
                        if ($hasPerm($perms['programs'][$prog->id] ?? [])) return true;
                    }
                }
            } elseif ($model instanceof Department) {
                $programs = (new Program())->get()->where(['department_id' => $model->id])->all();
                foreach ($programs as $prog) {
                    if ($hasPerm($perms['programs'][$prog->id] ?? [])) return true;
                }
            }
        }

        return false;
    }

    /**
     * Kullanıcının sistemin herhangi bir yerinde ilgili yetkiye sahip olup olmadığını kontrol eder.
     * Bu genellikle menü öğelerini göstermek veya genel yetki kontrolleri için kullanılır.
     *
     * @param int $userId
     * @param string $permission
     * @return bool
     */
    public static function hasAnyPermission(int $userId, string $permission): bool
    {
        $perms = self::getUserPermissions($userId);

        $impliedPermissions = [$permission];
        $manageProgramImplies = [PermissionType::MANAGE_PROGRAM->value, PermissionType::MANAGE_USERS->value, PermissionType::MANAGE_SCHEDULE->value, PermissionType::MANAGE_LESSONS->value];
        if (in_array($permission, $manageProgramImplies)) {
            $impliedPermissions[] = PermissionType::MANAGE_DEPARTMENT->value;
            $impliedPermissions[] = PermissionType::MANAGE_UNIT->value;
        }
        if ($permission === PermissionType::MANAGE_DEPARTMENT->value) {
            $impliedPermissions[] = PermissionType::MANAGE_UNIT->value;
        }

        foreach ($perms as $scope => $items) {
            foreach ($items as $id => $grantedPerms) {
                if (is_array($grantedPerms) && count(array_intersect($impliedPermissions, $grantedPerms)) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
