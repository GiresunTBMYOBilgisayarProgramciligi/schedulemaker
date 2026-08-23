<?php

namespace App\Repositories;

use App\Models\User;
use App\Enums\UserRole;
use App\Enums\UserTitle;
use App\Repositories\LessonAssignmentRepository;
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
            'lessons' => ['with' => ['department', 'program', 'parentLesson']], 
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
        $users = $model->get()->where(['department_id' => $deptId])->with(['department', 'program', 'unit'])->all();

        $affiliations = (new \App\Models\UserAffiliation())->get()->where(['department_id' => $deptId])->all();
        $affiliatedUserIds = array_unique(array_column($affiliations, 'user_id'));

        if (!empty($affiliatedUserIds)) {
            $affiliatedUsers = (new $this->modelClass)->get()->where([
                'id' => ['in' => $affiliatedUserIds]
            ])->with(['department', 'program', 'unit'])->all();

            $userIds = array_column($users, 'id');
            foreach ($affiliatedUsers as $au) {
                if (!in_array($au->id, $userIds)) {
                    $users[] = $au;
                }
            }
        }
        return $users;
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
        $users = $model->get()->where([
            'department_id' => $deptId, 
            '!role' => ["in" => [UserRole::User->value, UserRole::Admin->value]]
        ])->all();

        $affiliations = (new \App\Models\UserAffiliation())->get()->where(['department_id' => $deptId])->all();
        $affiliatedUserIds = array_unique(array_column($affiliations, 'user_id'));

        if (!empty($affiliatedUserIds)) {
            $affiliatedUsers = (new $this->modelClass)->get()->where([
                'id' => ['in' => $affiliatedUserIds],
                '!role' => ["in" => [UserRole::User->value, UserRole::Admin->value]]
            ])->all();

            $userIds = array_column($users, 'id');
            foreach ($affiliatedUsers as $au) {
                if (!in_array($au->id, $userIds)) {
                    $users[] = $au;
                }
            }
        }
        return $users;
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

    /**
     * Belirli bir birimdeki akademisyenleri (role != admin/user) getirir.
     *
     * @param int $unitId
     * @return User[]
     * @throws Exception
     */
    public function getLecturersByUnit(int $unitId): array
    {
        return $this->getFilteredLecturers($unitId, 0, 0);
    }

    /**
     * @param int $unitId
     * @param int $departmentId
     * @param int $programId
     * @return User[]
     * @throws Exception
     */
    public function getFilteredLecturers(int $unitId, int $departmentId, int $programId): array
    {
        $filters = ['!role' => ["in" => [UserRole::User->value, UserRole::Admin->value]]];
        
        if ($unitId > 0) $filters['unit_id'] = $unitId;
        if ($departmentId > 0) $filters['department_id'] = $departmentId;
        if ($programId > 0) $filters['program_id'] = $programId;

        $model = new $this->modelClass;
        $users = $model->get()->where($filters)->with(['unit', 'department'])->all();

        // Affiliations
        $affFilters = [];
        if ($unitId > 0) $affFilters['unit_id'] = $unitId;
        if ($departmentId > 0) $affFilters['department_id'] = $departmentId;
        if ($programId > 0) $affFilters['program_id'] = $programId;

        if (!empty($affFilters)) {
            $affiliations = (new \App\Models\UserAffiliation())->get()->where($affFilters)->all();
            $affiliatedUserIds = array_unique(array_column($affiliations, 'user_id'));

            if (!empty($affiliatedUserIds)) {
                $affiliatedUsers = (new $this->modelClass)->get()->where([
                    'id' => ['in' => $affiliatedUserIds],
                    '!role' => ["in" => [UserRole::User->value, UserRole::Admin->value]]
                ])->with(['unit', 'department'])->all();

                $userIds = array_column($users, 'id');
                foreach ($affiliatedUsers as $au) {
                    if (!in_array($au->id, $userIds)) {
                        $users[] = $au;
                    }
                }
            }
        }
        
        return $users;
    }

    /**
     * Akademisyenin aktif dönemdeki derslerini getirir.
     *
     * @param User|int $user Kullanıcı nesnesi veya ID'si
     * @return array Lesson nesneleri dizisi
     * @throws Exception
     */
    public function getActiveLessons(User|int $user): array
    {
        if ($user instanceof User && !empty($user->lessons)) {
            return $user->lessons;
        }

        $userId = $user instanceof User ? $user->id : $user;
        $activeAssignments = (new LessonAssignmentRepository())->findActiveAssignmentsForLecturer($userId);
        return array_values(array_filter(array_map(fn($a) => $a->lesson, $activeAssignments)));
    }

    /**
     * Verilen kullanıcı veya ders listesinden haftalık ders saatini hesaplar.
     * $withChild false ise birleştirilmiş (çocuk) dersler hesaba katılmaz, true ise katılır.
     *
     * @param User|array $userOrLessons Kullanıcı nesnesi veya Lesson nesneleri dizisi
     * @param bool $withChild Çocuk (birleştirilmiş) derslerin dahil edilip edilmeyeceği
     * @return int
     * @throws Exception
     */
    public function calculateWeeklyHours(User|array $userOrLessons, bool $withChild = false): int
    {
        if ($userOrLessons instanceof User) {
            return $userOrLessons->getWeeklyHours($withChild);
        }

        $user = new User();
        $user->lessons = $userOrLessons;
        return $user->getWeeklyHours($withChild);
    }

    /**
     * Verilen kullanıcı veya ders listesinden ders sayısını hesaplar.
     * $withChild false ise birleştirilmiş (çocuk) dersler hesaba katılmaz, true ise katılır.
     *
     * @param User|array $userOrLessons Kullanıcı nesnesi veya Lesson nesneleri dizisi
     * @param bool $withChild Çocuk (birleştirilmiş) derslerin dahil edilip edilmeyeceği
     * @return int
     * @throws Exception
     */
    public function calculateLessonCount(User|array $userOrLessons, bool $withChild = false): int
    {
        if ($userOrLessons instanceof User) {
            return $userOrLessons->getLessonCount($withChild);
        }

        $user = new User();
        $user->lessons = $userOrLessons;
        return $user->getLessonCount($withChild);
    }
}
