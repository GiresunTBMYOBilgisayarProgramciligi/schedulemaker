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
}
