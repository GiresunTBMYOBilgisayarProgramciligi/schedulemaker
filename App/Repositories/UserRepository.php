<?php

namespace App\Repositories;

use App\Models\User;
use App\Enums\UserRole;
use App\Enums\UserTitle;
use Exception;

class UserRepository extends BaseRepository
{
    protected string $modelClass = User::class;

    /**
     * E-posta adresine göre kullanıcı bulur.
     * 
     * @param string $email
     * @return User|null
     * @throws Exception
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['mail' => $email]);
    }

    /**
     * Sadece akademisyen olan (rolü user veya admin olmayan) kullanıcıların sayısını döner.
     * 
     * @return int
     * @throws Exception
     */
    public function getAcademicCount(): int
    {
        // Enum kullanarak 'user' ve 'admin' olmayanları sayıyoruz
        return $this->count([
            "!role" => ['in' => [UserRole::User->value, UserRole::Admin->value]]
        ]);
    }

    /**
     * İsim ve soyisme göre kullanıcı bulur (Örn: filtreler dizisi ile)
     * 
     * @param array $filters (örn: ['title' => 'Prof. Dr.', 'name' => 'Ali', 'last_name' => 'Yılmaz'])
     * @return User|null
     * @throws Exception
     */
    public function findByFullNameFilters(array $filters): ?User
    {
        return $this->findOneBy($filters);
    }

    /**
     * Akademik isim (Örn: "Prof. Dr. Ali Yılmaz") verilerek kullanıcıyı bulur.
     * 
     * @param string $fullName
     * @return User|null
     * @throws Exception
     */
    public function findByFullName(string $fullName): ?User
    {
        $filters = UserTitle::parseAcademicName($fullName);
        return $this->findByFullNameFilters($filters);
    }

    /**
     * Kullanıcının profil sayfası için gereken tüm ilişkisel detaylarını getirir.
     * (Bölüm, program, dersler ve ders programı öğeleri ile birlikte)
     *
     * @param int $id Kullanıcı ID'si
     * @return User|null
     * @throws Exception
     */
    public function findUserWithProfileDetails(int $id): ?User
    {
        /** @var User $model */
        $model = new $this->modelClass;
        return $model->get()->where(['id' => $id])->with([
            'department', 
            'program', 
            'lessons' => ['with' => ['department', 'program']], 
            'schedules' => ['with' => ['items']]
        ])->first();
    }

    /**
     * Bölüm başkanının görebileceği, kendi bölümüne ait kullanıcı listesini getirir.
     * 
     * @param int $deptId Bölüm ID'si
     * @return User[]
     * @throws Exception
     */
    public function getUsersForDepartmentHead(int $deptId): array
    {
        /** @var User $model */
        $model = new $this->modelClass;
        return $model->get()->where(['department_id' => $deptId])->with(['department', 'program', 'unit'])->all();
    }

    /**
     * Admin için tüm kullanıcıların detaylı listesini (bölüm ve program bilgisiyle) getirir.
     *
     * @return User[]
     * @throws Exception
     */
    public function getAllUsersWithDetails(): array
    {
        /** @var User $model */
        $model = new $this->modelClass;
        return $model->get()->with(['department', 'program', 'unit'])->all();
    }

    /**
     * Bölüm başkanının görebileceği, sadece kendi bölümündeki akademisyenleri (role != admin/user) getirir.
     *
     * @param int $deptId Bölüm ID'si
     * @return User[]
     * @throws Exception
     */
    public function getLecturersForDepartmentHead(int $deptId): array
    {
        /** @var User $model */
        $model = new $this->modelClass;
        return $model->get()->where([
            'department_id' => $deptId, 
            '!role' => ["in" => [UserRole::User->value, UserRole::Admin->value]]
        ])->all();
    }

    /**
     * Sistemdeki tüm akademisyenleri (role != admin/user) getirir.
     *
     * @return User[]
     * @throws Exception
     */
    public function getAllLecturers(): array
    {
        /** @var User $model */
        $model = new $this->modelClass;
        return $model->get()->where([
            '!role' => ["in" => [UserRole::User->value, UserRole::Admin->value]]
        ])->all();
    }
}
