<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Unit;
use App\Models\Department;
use App\Models\Program;
use App\Middlewares\AuthMiddleware;
use App\Enums\PermissionType;
use function App\Helpers\getSettingValue;

/**
 * Tüm yetki sınıfları için temel sınıf
 */
abstract class BasePolicy
{
    /**
     * Herhangi bir yetki kontrolünden önce çalıştırılır.
     * Eğer true dönerse yetki her durumda verilir (örn. Admin için).
     *
     * @param User|null $user
     * @param string $action
     * @return bool|null
     */
    public function before(?User $user, string $action): ?bool
    {
        if ($user && $user->role === 'admin') {
            return true;
        }
        return null;
    }

    /**
     * Sınıfta tanımlanmamış yetki istekleri (örn. manage_* eylemleri) için sihirli metod.
     * Politikada spesifik bir metod yoksa kaskad JSON yetki kontrolüne devreder.
     *
     * @param string $name
     * @param array $arguments
     * @return bool
     */
    public function __call(string $name, array $arguments): bool
    {
        $user = $arguments[0] ?? null;
        $model = $arguments[1] ?? null;

        if (!$user) {
            return false;
        }

        if (str_starts_with($name, 'manage_')) {
            return $this->hasCascadePermission($user, $name, $model);
        }

        return false;
    }

    /**
     * Kullanıcının özel yetkilerini Settings'ten JSON olarak alır
     * @param int|null $userId
     * @return array
     */
    public function getUserPermissions(?int $userId = null): array
    {
        if (is_null($userId)) {
            $user = AuthMiddleware::user();
            if (!$user) return [];
            $userId = $user->id;
        }

        $key = 'user_' . $userId;
        $perms = getSettingValue($key, 'permissions', []);

        if (!is_array($perms)) {
            $perms = [];
        }

        return $perms;
    }

    /**
     * Kullanıcının kaskad hiyerarşisinde (Birim -> Bölüm -> Program) ilgili yetkiye sahip olup olmadığını kontrol eder.
     *
     * Mantık Adımları:
     * 1. Hiyerarşik ID'ler tespit edilir (Program -> Bölüm -> Birim bağlantısı kurulur).
     * 2. İstenen yetkiyi sağlayan kapsayıcı üst yetkiler (Miras Listesi: örn. MANAGE_UNIT yetkisi MANAGE_DEPARTMENT ve MANAGE_SCHEDULE'u da kapsar) belirlenir.
     * 3. Yukarı Yönlü Kontrol (Parent): Kullanıcının bu hedef Program, Bölüm veya Birim seviyelerinin herhangi birinde yetkisi var mı bakılır.
     * 4. Aşağı Yönlü Kontrol (Child): Bir Birim veya Bölüm sorgulandığında, altındaki bölüm/programlarda kullanıcının tanımlı bir yetkisi var mı bakılır.
     *
     * @param User|int $user User nesnesi veya User ID
     * @param string $permission İstenen yetki türü (örn. 'manage_schedule')
     * @param mixed $model Model nesnesi (Unit, Department, Program vb.) veya null
     * @param array $data Opsiyonel manuel ID verileri
     * @return bool
     */
    public function hasCascadePermission(User|int $user, string $permission, $model = null, array $data = []): bool
    {
        $userId = $user instanceof User ? $user->id : (int) $user;
        $perms = $this->getUserPermissions($userId);

        // --- Adım 1: Hiyerarşik ID'leri tespit et ---
        $unitId = $data['unit_id'] ?? null;
        $departmentId = $data['department_id'] ?? null;
        $programId = $data['program_id'] ?? null;

        if ($model) {
            $programId = $programId ?? ($model->program_id ?? ($model instanceof Program ? $model->id : null));
            $departmentId = $departmentId ?? ($model->department_id ?? ($model instanceof Department ? $model->id : null));
            $unitId = $unitId ?? ($model->unit_id ?? ($model instanceof Unit ? $model->id : null));
        }

        if ($programId && !$departmentId) {
            $departmentId = (new Program())->find($programId)?->department_id;
        }
        if ($departmentId && !$unitId) {
            $unitId = (new Department())->find($departmentId)?->unit_id;
        }

        // Spesifik bir hedef (Birim/Bölüm/Program) belirtilmemişse genel yetki var mı diye bakılır
        if (!$programId && !$departmentId && !$unitId) {
            return $this->hasAnyPermission($userId, $permission);
        }

        // --- Adım 2: İstenen yetkiyi kapsayan üst yetkileri tanımla (Kaskad Miras Eşleşmesi) ---
        $impliedPermissions = match ($permission) {
            PermissionType::MANAGE_DEPARTMENT->value => [$permission, PermissionType::MANAGE_UNIT->value],
            PermissionType::MANAGE_PROGRAM->value,
            PermissionType::MANAGE_USERS->value,
            PermissionType::MANAGE_SCHEDULE->value,
            PermissionType::MANAGE_LESSONS->value => [$permission, PermissionType::MANAGE_DEPARTMENT->value, PermissionType::MANAGE_UNIT->value],
            default => [$permission]
        };

        $hasPerm = static function(?array $granted) use ($impliedPermissions): bool {
            return is_array($granted) && count(array_intersect($granted, $impliedPermissions)) > 0;
        };

        // --- Adım 3: Yukarı Yönlü (Parent) Kontrol ---
        // Kullanıcının Program, Bölüm veya Birim seviyesinde yetkisi var mı?
        if (($programId && $hasPerm($perms['programs'][$programId] ?? null)) ||
            ($departmentId && $hasPerm($perms['departments'][$departmentId] ?? null)) ||
            ($unitId && $hasPerm($perms['units'][$unitId] ?? null))) {
            return true;
        }

        // --- Adım 4: Aşağı Yönlü (Child Cascade) Kontrol ---
        // Üst seviye (Birim/Bölüm) sorgulanırken, kullanıcının alt birimlerde yetkisi var mı?
        if ($model instanceof Unit) {
            $departments = (new Department())->get()->where(['unit_id' => $model->id])->all();
            foreach ($departments as $dept) {
                if ($hasPerm($perms['departments'][$dept->id] ?? null)) return true;
                $programs = (new Program())->get()->where(['department_id' => $dept->id])->all();
                foreach ($programs as $prog) {
                    if ($hasPerm($perms['programs'][$prog->id] ?? null)) return true;
                }
            }
        } elseif ($model instanceof Department) {
            $programs = (new Program())->get()->where(['department_id' => $model->id])->all();
            foreach ($programs as $prog) {
                if ($hasPerm($perms['programs'][$prog->id] ?? null)) return true;
            }
        }

        return false;
    }

    /**
     * Kullanıcının sistemin herhangi bir yerinde (Birim, Bölüm veya Program fark etmeksizin) ilgili yetkiye sahip olup olmadığını kontrol eder.
     * Genellikle menü görünürlüğü ve genel yetki kontrollerinde kullanılır.
     *
     * @param User|int $user
     * @param string $permission
     * @return bool
     */
    public function hasAnyPermission(User|int $user, string $permission): bool
    {
        $userId = $user instanceof User ? $user->id : (int) $user;
        $perms = $this->getUserPermissions($userId);

        $impliedPermissions = match ($permission) {
            PermissionType::MANAGE_DEPARTMENT->value => [$permission, PermissionType::MANAGE_UNIT->value],
            PermissionType::MANAGE_PROGRAM->value,
            PermissionType::MANAGE_USERS->value,
            PermissionType::MANAGE_SCHEDULE->value,
            PermissionType::MANAGE_LESSONS->value => [$permission, PermissionType::MANAGE_DEPARTMENT->value, PermissionType::MANAGE_UNIT->value],
            default => [$permission]
        };

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

