<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\UserAffiliation;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Enums\UserRole;
use App\Enums\UserTitle;
use App\Middlewares\AuthMiddleware;
use App\Repositories\LessonAssignmentRepository;
use Exception;

class UserRepository extends BaseRepository
{
    protected string $modelClass = User::class;

    /**
     * Yetkilendirilmiş veya sistemdeki akademisyenleri birim ve bölüm ilişkileriyle birlikte getirir.
     *
     * @param User|null $currentUser
     * @param array $conditions
     * @param array $with
     * @return User[]
     * @throws Exception
     */
    public function getAuthorizedLecturersWithHierarchy(?User $currentUser = null, array $conditions = [], array $with = []): array
    {
        $currentUser = $currentUser ?? AuthMiddleware::user();

        if (!isset($conditions['role'])) {
            $conditions['role'] = ['in' => UserRole::getAcademicRoles()];
        }

        if ($currentUser && $currentUser->role === UserRole::DepartmentHead->value && !empty($currentUser->department_id)) {
            if (!isset($conditions['department_id'])) {
                $conditions['department_id'] = $currentUser->department_id;
            }
        }

        $relations = array_merge(['department' => ['with' => ['unit']], 'unit'], $with);

        return $this->getAuthorized('view', $conditions, $relations);
    }

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
        return $this->count([
            "role" => ['in' => UserRole::getAcademicRoles()]
        ]);
    }

    /**
     * Belirtilen dönemde kullanıcıların sınav gözetmenlik görevi sayılarını sınav türüne göre döner.
     *
     * Görevler, sınav kaydedilirken gözetmenin kendi (owner_type = 'user') takvimine yazılan
     * ve detail alanında reference_type = 'exam_assignment' bulunan item'lardan sayılır.
     * Hiç görevi olmayan akademisyenler de 0 değeriyle listeye dahil edilir.
     * Birim/bölüm filtresi kullanıcının kadro birimi/bölümü (users.unit_id / users.department_id) üzerinden uygulanır.
     *
     * @param string $semester
     * @param string $academicYear
     * @param int|null $unitId
     * @param int|null $departmentId
     * @return array<int, array{id:int, full_name:string, department_name:?string,
     *               by_type:array<string,int>, total:int, total_minutes:int}> by_type ExamType değerleriyle anahtarlanır
     */
    public function getExamDutyStats(string $semester, string $academicYear, ?int $unitId = null, ?int $departmentId = null): array
    {
        $examTypes = ExamType::values();
        $academicRoles = UserRole::getAcademicRoles();
        $placeholders = fn(array $values) => implode(',', array_fill(0, count($values), '?'));

        // Her sınav türü için ayrı sayaç kolonu (type_0, type_1, ...)
        $typeColumns = [];
        $outerTypeColumns = [];
        foreach (array_keys($examTypes) as $i) {
            $typeColumns[] = "SUM(s.type = ?) AS type_$i";
            $outerTypeColumns[] = "COALESCE(duty.type_$i, 0) AS type_$i";
        }

        $filterSql = '';
        $filterParams = [];
        foreach (['unit_id' => $unitId, 'department_id' => $departmentId] as $column => $value) {
            if (!$value) {
                continue;
            }
            $filterSql .= " AND u.$column = ?";
            $filterParams[] = $value;
        }

        $stmt = $this->db->prepare("
            SELECT u.id, u.title, u.name, u.last_name, d.name AS department_name,
                   " . implode(', ', $outerTypeColumns) . ",
                   COALESCE(duty.total, 0) AS total,
                   COALESCE(duty.total_minutes, 0) AS total_minutes
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            LEFT JOIN (
                SELECT s.owner_id AS user_id,
                       " . implode(', ', $typeColumns) . ",
                       COUNT(*) AS total,
                       SUM(TIME_TO_SEC(TIMEDIFF(si.end_time, si.start_time))) DIV 60 AS total_minutes
                FROM schedule_items si
                INNER JOIN schedules s ON s.id = si.schedule_id
                WHERE s.owner_type = ?
                  AND s.type IN (" . $placeholders($examTypes) . ")
                  AND s.semester = ?
                  AND s.academic_year = ?
                  AND si.detail LIKE '%\"exam_assignment\"%'
                GROUP BY s.owner_id
            ) duty ON duty.user_id = u.id
            WHERE (u.role IN (" . $placeholders($academicRoles) . ") OR duty.user_id IS NOT NULL)
              $filterSql
            ORDER BY total DESC, u.name, u.last_name
        ");
        $stmt->execute(array_merge(
            $examTypes,
            [OwnerType::USER->value],
            $examTypes,
            [$semester, $academicYear],
            $academicRoles,
            $filterParams
        ));

        return array_map(fn(array $row) => [
            'id'              => (int)$row['id'],
            'full_name'       => trim($row['title'] . ' ' . $row['name'] . ' ' . $row['last_name']),
            'department_name' => $row['department_name'],
            'by_type'         => array_combine($examTypes, array_map(fn($i) => (int)$row["type_$i"], array_keys($examTypes))),
            'total'           => (int)$row['total'],
            'total_minutes'   => (int)$row['total_minutes'],
        ], $stmt->fetchAll(\PDO::FETCH_ASSOC));
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

        $affiliations = (new UserAffiliation())->get()->where(['department_id' => $deptId])->all();
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
            'role' => ["in" => UserRole::getAcademicRoles()]
        ])->all();

        $affiliations = (new UserAffiliation())->get()->where(['department_id' => $deptId])->all();
        $affiliatedUserIds = array_unique(array_column($affiliations, 'user_id'));

        if (!empty($affiliatedUserIds)) {
            $affiliatedUsers = (new $this->modelClass)->get()->where([
                'id' => ['in' => $affiliatedUserIds],
                'role' => ["in" => UserRole::getAcademicRoles()]
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
     * Sistemdeki tüm akademisyenleri getirir.
     *
     * @param array $with Eager load edilecek ilişkiler (örn: ['unit'])
     * @return User[]
     * @throws Exception
     */
    public function getAllLecturers(array $with = []): array
    {
        /** @var User $model */
        $model = new $this->modelClass;
        $query = $model->get()->where([
            'role' => ["in" => UserRole::getAcademicRoles()]
        ]);

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->all();
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
        $filters = ['role' => ["in" => UserRole::getAcademicRoles()]];
        
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
            $affiliations = (new UserAffiliation())->get()->where($affFilters)->all();
            $affiliatedUserIds = array_unique(array_column($affiliations, 'user_id'));

            if (!empty($affiliatedUserIds)) {
                $affiliatedUsers = (new $this->modelClass)->get()->where([
                    'id' => ['in' => $affiliatedUserIds],
                    'role' => ["in" => UserRole::getAcademicRoles()]
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

    /**
     * Akademik personeli akademik unvan kıdemine (Prof > Doç > Dr. Öğr. Üyesi ...)
     * ve ardından alfabetik ada göre sıralanmış olarak döndürür.
     *
     * @return User[]
     * @throws Exception
     */
    public function getSortedAcademicStaff(): array
    {
        $lecturers = (new User())->get()->where([
            'role' => ['in' => UserRole::getAcademicRoles()]
        ])->with(['unit'])->all();

        usort($lecturers, function (User $a, User $b) {
            $rankA = UserTitle::tryFrom((string)$a->title)?->getHierarchyRank() ?? 0;
            $rankB = UserTitle::tryFrom((string)$b->title)?->getHierarchyRank() ?? 0;
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA; // Yüksek unvan önce (azalan)
            }
            return strcmp(
                mb_strtolower($a->name . ' ' . $a->last_name, 'UTF-8'),
                mb_strtolower($b->name . ' ' . $b->last_name, 'UTF-8')
            );
        });

        return $lecturers;
    }

    /**
     * Kullanıcının son giriş zamanını (last_login) günceller.
     *
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin(int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    /**
     * Kullanıcının şifresini günceller.
     *
     * @param int $userId
     * @param string $hashedPassword
     * @return bool
     */
    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $userId]);
    }
}
