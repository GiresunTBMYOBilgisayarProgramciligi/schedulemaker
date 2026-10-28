<?php

namespace App\Services\Schedule;

use App\Services\BaseService;
use App\Models\Schedule;
use App\Models\ScheduleChangeQueue;
use App\Repositories\ScheduleRepository;
use App\Core\EventDispatcher;
use App\Events\ScheduleChangesNotifiedEvent;
use App\Events\SchedulePublishedEvent;
use App\Models\Department;
use App\Models\Program;
use App\Models\Lesson;
use App\Models\LessonAssignment;
use App\Models\Unit;
use App\Models\Building;
use App\Models\Classroom;
use App\Enums\OwnerType;
use App\Core\Gate;
use App\Enums\PermissionType;
use App\Models\User;
use App\Mailers\ScheduleMailer;
use Exception;
use function App\Helpers\getSettingValue;

class SchedulePublishService extends BaseService
{
    protected string $logChannel = 'schedule';
    /**
     * @throws Exception
     */
    public function togglePublish(int $scheduleId): array
    {
        /** @var Schedule|null $schedule */
        $schedule = (new ScheduleRepository())->find($scheduleId);
        if (!$schedule) {
            throw new Exception("Program bulunamadı");
        }

        $publishStatus = !$schedule->is_published;
        $result = $this->applyPublishStatus($schedule, $publishStatus);

        $crossUnitNotified = 0;
        $staffNotified = 0;
        if ($publishStatus && $schedule->owner_type === OwnerType::PROGRAM->value) {
            ['cross_unit' => $crossUnitNotified, 'staff' => $staffNotified] = $this->notifyUsersForProgramSchedule($schedule);
        }

        $actionText = $publishStatus ? "yayınlandı" : "yayından kaldırıldı";
        $screenName = $schedule->getScheduleScreenName();
        $this->logger->info("Program ($screenName) {$actionText}.", $this->logContext([
            'schedule_id' => $schedule->id,
            'is_published' => $publishStatus,
            'notified_users' => $result['notified_users'],
            'cross_unit_notified' => $crossUnitNotified,
            'staff_notified' => $staffNotified
        ]));

        $msg = "Program " . $actionText;
        if ($publishStatus && $result['notified_users'] > 0) {
            $msg .= " (hocaya e-posta gönderildi)";
        }
        if ($publishStatus && $crossUnitNotified > 0) {
            $msg .= " (Farklı birimden $crossUnitNotified hocaya bilgilendirme e-postası gönderildi)";
        }
        if ($publishStatus && $staffNotified > 0) {
            $msg .= " (Kadrodaki $staffNotified hocaya bilgilendirme e-postası gönderildi)";
        }

        return [
            "status" => "success",
            "msg" => $msg,
            "is_published" => $publishStatus,
            "notified_users" => $result['notified_users'],
            "cross_unit_notified" => $crossUnitNotified,
            "staff_notified" => $staffNotified,
            "is_test_mode" => (getSettingValue('mail_driver', 'mail', 'log') !== 'smtp')
        ];
    }

    /**
     * Tek bir schedule'ın yayın durumunu günceller. Yayınlanıyorsa SchedulePublishedEvent tetikler
     * (hoca programı için e-posta kuyruğa eklenir). Tüm yayınlama yolları bu metodu kullanır.
     *
     * @return array{notified_users: int}
     */
    private function applyPublishStatus(Schedule $schedule, bool $publishStatus): array
    {
        $schedule->is_published = $publishStatus ? 1 : 0;
        $schedule->published_at = $publishStatus ? date('Y-m-d H:i:s') : null;
        $schedule->update();

        $notifiedUsers = 0;
        if ($publishStatus) {
            EventDispatcher::getInstance()->dispatch(new SchedulePublishedEvent($schedule->id));
            if ($schedule->owner_type === OwnerType::USER->value) {
                $notifiedUsers = 1;
            }
        }

        return ['notified_users' => $notifiedUsers];
    }

    /**
     * Program programı yayınlandığında hocalara bilgilendirme e-postası gönderir:
     *  - Programın derslerine giren ancak kadrosunda olmayan (farklı birim/bölüm/program) hocalar
     *  - Programa kadrolu hocalar (hoca programları ayrıca yayınlanana kadar ek içermeyen bilgilendirme)
     *
     * @return array{cross_unit: int, staff: int}
     */
    private function notifyUsersForProgramSchedule(Schedule $schedule): array
    {
        $period = [(string)$schedule->semester, (string)$schedule->academic_year, (string)$schedule->type];
        $scheduleIdsData = $this->collectScheduleIdsByScope('program', (int)$schedule->owner_id, ...$period);

        return [
            'cross_unit' => $this->notifyCrossUnitUsers($scheduleIdsData['cross_unit_users'], 'program', (int)$schedule->owner_id, ...$period),
            'staff'      => $this->notifyStaffUsers($scheduleIdsData['staff_users'], 'program', (int)$schedule->owner_id, ...$period),
        ];
    }

    /**
     * İçerisinde hiçbir öğe (schedule_item) bulunmayan boş schedule kayıtlarını temizler.
     * 
     * @param string|null $semester
     * @param string|null $academicYear
     * @param string|null $type
     * @return int Silinen boş schedule sayısı
     */
    public function cleanEmptySchedules(?string $semester = null, ?string $academicYear = null, ?string $type = null): int
    {
        $deletedCount = (new ScheduleRepository())->deleteEmptySchedules($semester, $academicYear, $type);
        if ($deletedCount > 0) {
            $this->logger->info("Boş schedule kayıtları temizlendi: {$deletedCount} adet kayıt silindi.", $this->logContext([
                'deleted_count' => $deletedCount,
                'semester' => $semester,
                'academic_year' => $academicYear,
                'type' => $type
            ]));
        }
        return $deletedCount;
    }

    public function getPublishStats(?string $semester = null, ?string $academicYear = null, ?string $type = null): array
    {
        $semester = $semester ?? getSettingValue('semester');
        $academicYear = $academicYear ?? getSettingValue('academic_year');

        $whereTotal = [
            'semester' => $semester,
            'academic_year' => $academicYear,
            'owner_type' => ['!=' => OwnerType::LESSON->value]
        ];
        
        if ($type) {
            $whereTotal['type'] = $type;
        }

        $totalCount = (new Schedule())->get()->where($whereTotal)->count();

        $whereUnpublished = array_merge($whereTotal, ['is_published' => 0]);
        $unpublishedCount = (new Schedule())->get()->where($whereUnpublished)->count();

        return [
            'total_count' => $totalCount,
            'unpublished_count' => $unpublishedCount,
            'all_published' => $totalCount > 0 && $unpublishedCount === 0
        ];
    }

    /**
     * Hiyerarşik scope'a göre schedule ID'lerini toplar
     * @param string $scope 'unit', 'department', 'program'
     * @param int $scopeId
     * @param string $semester
     * @param string $academicYear
     * @param string $type
     * @return array ['program' => [ids], 'lesson' => [ids], 'classroom' => [ids], 'user' => [ids], 'cross_unit_users' => [user_id => [lesson_ids]], 'staff_users' => [user_ids]]
     * @throws Exception
     */
    private function collectScheduleIdsByScope(
        string $scope,
        int $scopeId,
        string $semester,
        string $academicYear,
        string $type
    ): array {
        $result = [
            OwnerType::PROGRAM->value => [],
            OwnerType::CLASSROOM->value => [],
            OwnerType::USER->value => [],
            'cross_unit_users' => [],
            'staff_users' => []
        ];

        $departmentIds = [];
        $programIds = [];
        $lessonIds = [];
        $classroomIds = [];
        $userIds = [];
        $crossUnitUsers = [];

        // 1. İlgili Entity ID'lerini Topla
        if ($scope === 'unit') {
            $departments = (new Department())->get()->where(['unit_id' => $scopeId])->all();
            $departmentIds = array_map(fn($d) => $d->id, $departments);

            if (!empty($departmentIds)) {
                $programs = (new Program())->get()->where(['department_id' => ['in' => $departmentIds]])->all();
                $programIds = array_map(fn($p) => $p->id, $programs);

                $lessons = (new Lesson())->get()->where(['department_id' => ['in' => $departmentIds]])->all();
                $lessonIds = array_map(fn($l) => $l->id, $lessons);
            }

            $buildings = (new Building())->get()->where(['unit_id' => $scopeId])->all();
            $buildingIds = array_map(fn($b) => $b->id, $buildings);
            if (!empty($buildingIds)) {
                $classrooms = (new Classroom())->get()->where(['building_id' => ['in' => $buildingIds]])->all();
                $classroomIds = array_map(fn($c) => $c->id, $classrooms);
            }

            // Birime, birimin bölümlerine veya birimin programlarına kadrolu olan hocalar
            $unitUsers = (new User())->get()->where(['unit_id' => $scopeId])->all();
            $userIds = array_map(fn($u) => $u->id, $unitUsers);
            if (!empty($departmentIds)) {
                $deptUsers = (new User())->get()->where(['department_id' => ['in' => $departmentIds]])->all();
                $userIds = array_merge($userIds, array_map(fn($u) => $u->id, $deptUsers));
            }
            if (!empty($programIds)) {
                $progUsers = (new User())->get()->where(['program_id' => ['in' => $programIds]])->all();
                $userIds = array_merge($userIds, array_map(fn($u) => $u->id, $progUsers));
            }

        } elseif ($scope === 'department') {
            $departmentIds = [$scopeId];

            $programs = (new Program())->get()->where(['department_id' => $scopeId])->all();
            $programIds = array_map(fn($p) => $p->id, $programs);

            $lessons = (new Lesson())->get()->where(['department_id' => $scopeId])->all();
            $lessonIds = array_map(fn($l) => $l->id, $lessons);

            // Bölüme veya bölümün programlarına kadrolu olan hocalar
            $deptUsers = (new User())->get()->where(['department_id' => $scopeId])->all();
            $userIds = array_map(fn($u) => $u->id, $deptUsers);
            if (!empty($programIds)) {
                $progUsers = (new User())->get()->where(['program_id' => ['in' => $programIds]])->all();
                $userIds = array_merge($userIds, array_map(fn($u) => $u->id, $progUsers));
            }

        } elseif ($scope === 'program') {
            $programIds = [$scopeId];

            $lessons = (new Lesson())->get()->where(['program_id' => $scopeId])->all();
            $lessonIds = array_map(fn($l) => $l->id, $lessons);

            // Sadece bu programa kadrolu olan hocalar (users.program_id)
            $progUsers = (new User())->get()->where(['program_id' => $scopeId])->all();
            $userIds = array_map(fn($u) => $u->id, $progUsers);
        } elseif ($scope === 'user' || $scope === 'user_single') {
            $userIds = [$scopeId];
        } elseif ($scope === 'classroom' || $scope === 'classroom_single') {
            $classroomIds = [$scopeId];
        } elseif ($scope === 'building') {
            $classrooms = (new Classroom())->get()->where(['building_id' => $scopeId])->all();
            $classroomIds = array_map(fn($c) => $c->id, $classrooms);
        }
        
        $userIds = array_values(array_unique(array_filter($userIds)));

        // 2. Schedule'ları Bul
        $scheduleModel = new Schedule();
        $baseWhere = [
            'semester' => $semester,
            'academic_year' => $academicYear,
            'type' => $type
        ];

        $ownerMap = [
            OwnerType::PROGRAM->value => $programIds,
            OwnerType::CLASSROOM->value => $classroomIds,
            OwnerType::USER->value => $userIds
        ];

        foreach ($ownerMap as $ownerType => $ids) {
            if (!empty($ids)) {
                $where = array_merge($baseWhere, ['owner_type' => $ownerType, 'owner_id' => ['in' => $ids]]);
                $schedules = $scheduleModel->get()->where($where)->all();
                $result[$ownerType] = array_map(fn($s) => $s->id, $schedules);
            }
        }

        // 3. Farklı Birim / Bölüm / Program Hocalarını Bul
        // Yayınlanacak derslere (lessonIds) giren ama bu kapsamın kadrosunda OLMAYAN (userIds içinde yer almayan) hocalar
        if (!empty($lessonIds)) {
            $assignments = (new LessonAssignment())->get()->where([
                'lesson_id' => ['in' => $lessonIds],
                'semester' => $semester,
                'academic_year' => $academicYear
            ])->all();
            foreach ($assignments as $assignment) {
                if ($assignment->lecturer_id && !in_array($assignment->lecturer_id, $userIds)) {
                    if (!isset($crossUnitUsers[$assignment->lecturer_id])) {
                        $crossUnitUsers[$assignment->lecturer_id] = [];
                    }
                    $crossUnitUsers[$assignment->lecturer_id][] = $assignment->lesson_id;
                }
            }
        }
        $result['cross_unit_users'] = $crossUnitUsers;
        $result['staff_users'] = $userIds;

        return $result;
    }

    /**
     * Scope bazlı toplu yayınlama
     * @throws Exception
     */
    public function bulkPublishByScope(
        string $scope,
        int $scopeId,
        string $semester,
        string $academicYear,
        string $type,
        bool $publishStatus = true,
        ?string $ownerTypeTab = null
    ): array {
        // Yayınlama öncesinde boş schedule kayıtlarını temizle
        if ($publishStatus) {
            $this->cleanEmptySchedules($semester, $academicYear, $type);
        }

        $scheduleIdsData = $this->collectScheduleIdsByScope($scope, $scopeId, $semester, $academicYear, $type);
        $scheduleIdsToProcess = $this->filterScheduleIdsByTab($scheduleIdsData, $ownerTypeTab, $scope);

        if (empty($scheduleIdsToProcess)) {
            return ['count' => 0, 'notified_users' => 0, 'cross_unit_notified' => 0, 'staff_notified' => 0];
        }

        $schedules = (new Schedule())->get()->where(['id' => ['in' => $scheduleIdsToProcess]])->all();
        $count = 0;
        $notifiedUsers = 0;

        foreach ($schedules as $schedule) {
            // Yetki kontrolü
            if (!Gate::check(PermissionType::PUBLISH_SCHEDULE->value, $schedule)) {
                continue;
            }

            if ((bool)$schedule->is_published !== $publishStatus) {
                $result = $this->applyPublishStatus($schedule, $publishStatus);
                $count++;
                $notifiedUsers += $result['notified_users'];
            }
        }

        $crossUnitNotified = 0;
        $staffNotified = 0;
        if ($publishStatus && $ownerTypeTab === null) {
            $crossUnitNotified = $this->notifyCrossUnitUsers($scheduleIdsData['cross_unit_users'], $scope, $scopeId, $semester, $academicYear, $type);
            // Kadrolu hocalar yalnızca bu işlemde gerçekten yayınlanan program olduğunda bilgilendirilir (tekrar basışta mükerrer e-posta gitmez)
            if ($count > 0 && in_array($scope, ['unit', 'department', 'program'], true)) {
                $staffNotified = $this->notifyStaffUsers($scheduleIdsData['staff_users'], $scope, $scopeId, $semester, $academicYear, $type);
            }
        }

        $actionText = $publishStatus ? "yayınlandı" : "yayından kaldırıldı";
        $this->logger->info("Toplu program işlemi ($scope:$scopeId): {$count} adet program {$actionText}.", $this->logContext([
            'count' => $count,
            'scope' => $scope,
            'scope_id' => $scopeId,
            'publish_status' => $publishStatus,
            'staff_notified' => $staffNotified
        ]));

        return [
            'count' => $count,
            'notified_users' => $notifiedUsers,
            'cross_unit_notified' => $crossUnitNotified,
            'staff_notified' => $staffNotified
        ];
    }
    
    /**
     * Farklı birim hocalarına bildirim
     */
    private function notifyCrossUnitUsers(array $crossUnitUsers, string $scope, int $scopeId, string $semester, string $academicYear, string $type): int
    {
        $count = 0;
        if (empty($crossUnitUsers)) {
            return $count;
        }

        ['unit' => $unitName, 'department' => $departmentName, 'program' => $programName] = $this->resolveScopeNames($scope, $scopeId);
        $typeLabel = $this->getScheduleTypeLabel($type);

        $mailer = new ScheduleMailer();

        foreach ($crossUnitUsers as $lecturerId => $lessonIds) {
            $lecturer = (new User())->find($lecturerId);
            if ($lecturer && !empty($lecturer->mail)) {
                $lessonNames = [];
                if (!empty($lessonIds)) {
                    $lessons = (new Lesson())->get()->where(['id' => ['in' => $lessonIds]])->all();
                    $lessonNames = array_map(fn($l) => ($l->code ? "{$l->code} - " : "") . $l->name, $lessons);
                }

                $queueId = $mailer->queueCrossUnitNotification(
                    $lecturer,
                    $unitName,
                    $typeLabel,
                    $semester,
                    $academicYear,
                    $departmentName ?: null,
                    $programName ?: null,
                    $lessonNames
                );
                if ($queueId > 0) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Kapsama kadrolu akademik personele kapsamın programının yayınlandığını bildirir (ek içermeyen e-posta).
     *
     * @param int[] $staffUserIds
     */
    private function notifyStaffUsers(array $staffUserIds, string $scope, int $scopeId, string $semester, string $academicYear, string $type): int
    {
        if (empty($staffUserIds)) {
            return 0;
        }

        ['unit' => $unitName, 'department' => $departmentName, 'program' => $programName] = $this->resolveScopeNames($scope, $scopeId);
        $typeLabel = $this->getScheduleTypeLabel($type);
        $mailer = new ScheduleMailer();

        $count = 0;
        foreach ((new User())->get()->where(['id' => ['in' => $staffUserIds]])->all() as $lecturer) {
            if (!$lecturer->isAcademic() || empty($lecturer->mail)) {
                continue;
            }
            $queueId = $mailer->queueAffiliationPublishedNotification(
                $lecturer,
                $scope,
                $typeLabel,
                $semester,
                $academicYear,
                $unitName,
                $departmentName ?: null,
                $programName ?: null
            );
            if ($queueId > 0) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Yayınlama kapsamının birim, bölüm ve program adlarını döndürür.
     *
     * @return array{unit: string, department: string, program: string}
     */
    private function resolveScopeNames(string $scope, int $scopeId): array
    {
        $names = ['unit' => '', 'department' => '', 'program' => ''];

        if ($scope === 'unit') {
            $names['unit'] = (new Unit())->find($scopeId)?->name ?? 'Birim';
        } elseif ($scope === 'department') {
            $dept = (new Department())->find($scopeId);
            $names['department'] = $dept?->name ?? '';
            $names['unit'] = $dept ? ((new Unit())->find($dept->unit_id)?->name ?? '') : '';
        } elseif ($scope === 'program') {
            $prog = (new Program())->find($scopeId);
            $names['program'] = $prog?->name ?? '';
            $dept = $prog ? (new Department())->find($prog->department_id) : null;
            $names['department'] = $dept?->name ?? '';
            $names['unit'] = $dept ? ((new Unit())->find($dept->unit_id)?->name ?? '') : '';
        }

        return $names;
    }

    private function getScheduleTypeLabel(string $type): string
    {
        $scheduleModel = new Schedule();
        $scheduleModel->type = $type;
        return $scheduleModel->getScheduleTypeName();
    }

    /**
     * Sekme (Tab) seçimine göre işlenecek schedule ID'lerini filtreler.
     *
     * Sekme verilmezse (yayınlama sayfasındaki Program sekmesi) program ve derslik programları işlenir.
     * Hoca programları program yayınıyla birlikte yayınlanmaz; yalnızca Hoca sekmesinden veya
     * hoca kapsamından (user, user_single) yayınlanır.
     */
    private function filterScheduleIdsByTab(array $scheduleIdsData, ?string $ownerTypeTab, string $scope): array
    {
        if ($ownerTypeTab !== null) {
            return $scheduleIdsData[$ownerTypeTab] ?? [];
        }
        if (in_array($scope, ['user', 'user_single'], true)) {
            return $scheduleIdsData[OwnerType::USER->value] ?? [];
        }
        return array_merge(
            $scheduleIdsData[OwnerType::PROGRAM->value] ?? [],
            $scheduleIdsData[OwnerType::CLASSROOM->value] ?? []
        );
    }

    /**
     * Scope bazlı yayın istatistikleri
     * @throws Exception
     */
    public function getPublishStatsByScope(
        string $scope,
        int $scopeId,
        string $semester,
        string $academicYear,
        string $type,
        ?string $ownerTypeTab = null
    ): array {
        $scheduleIdsData = $this->collectScheduleIdsByScope($scope, $scopeId, $semester, $academicYear, $type);
        $scheduleIdsToProcess = $this->filterScheduleIdsByTab($scheduleIdsData, $ownerTypeTab, $scope);

        if (empty($scheduleIdsToProcess)) {
            return [
                'total_count' => 0,
                'published_count' => 0,
                'unpublished_count' => 0,
                'all_published' => false,
                'details' => []
            ];
        }

        $totalCount = count($scheduleIdsToProcess);
        
        $schedules = (new Schedule())->get()->where(['id' => ['in' => $scheduleIdsToProcess]])->all();
        $publishedCount = 0;
        $details = [];
        foreach ($schedules as $s) {
            if ($s->is_published) {
                $publishedCount++;
            }
            $details[] = [
                'id' => $s->id,
                'schedule_id' => $s->id,
                'type' => $s->type,
                'type_label' => $s->getScheduleTypeName(),
                'owner_type' => $s->owner_type,
                'owner_id' => $s->owner_id,
                'name' => $s->getScheduleScreenName(),
                'is_published' => (bool)$s->is_published
            ];
        }

        $unpublishedCount = $totalCount - $publishedCount;

        return [
            'total_count' => $totalCount,
            'published_count' => $publishedCount,
            'unpublished_count' => $unpublishedCount,
            'all_published' => ($totalCount > 0 && $totalCount === $publishedCount),
            'details' => $details
        ];
    }

    /**
     * Bekleyen değişiklikleri öğretim elemanlarına göre gruplayarak getirir.
     *
     * @return array<int, array{id: int, name: string, email: string, unit_name: ?string, department_name: ?string, program_name: ?string, change_count: int, changes: array}>
     */
    public function getPendingChangesGroupedByLecturer(): array
    {
        $queueModel = new ScheduleChangeQueue();
        /** @var ScheduleChangeQueue[] $changes */
        $changes = $queueModel->get()->all();
        if (empty($changes)) {
            return [];
        }

        $lecturerIds = array_values(array_filter(array_unique(array_map(fn($c) => $c->lecturer_id, $changes))));
        if (empty($lecturerIds)) {
            return [];
        }

        /** @var User[] $users */
        $users = (new User())->get()
            ->where(['id' => ['in' => $lecturerIds]])
            ->with(['unit', 'department', 'program'])
            ->all();

        $usersKeyed = [];
        foreach ($users as $user) {
            $usersKeyed[$user->id] = $user;
        }

        $grouped = [];
        foreach ($changes as $change) {
            $lecturerId = $change->lecturer_id;
            if (!$lecturerId || !isset($usersKeyed[$lecturerId])) {
                continue;
            }

            $user = $usersKeyed[$lecturerId];

            if (!isset($grouped[$lecturerId])) {
                $grouped[$lecturerId] = [
                    'id'              => $user->id,
                    'name'            => $user->getFullName(),
                    'email'           => $user->mail ?? '',
                    'unit_name'       => $user->unit?->name,
                    'department_name' => $user->department?->name,
                    'program_name'    => $user->program?->name,
                    'change_count'    => 0,
                    'changes'         => []
                ];
            }

            $grouped[$lecturerId]['change_count']++;
            $grouped[$lecturerId]['changes'][] = [
                'id'          => $change->id,
                'action_type' => $change->action_type,
                'detail'      => $change->detail,
                'created_at'  => $change->created_at,
                'schedule_id' => $change->schedule_id
            ];
        }

        return array_values($grouped);
    }

    /**
     * @param array<int>|null $lecturerIds Belirtilmişse sadece bu hocalara bildirim gönderilir
     * @return int
     * @throws Exception
     */
    public function notifyChanges(?array $lecturerIds = null): int
    {
        $queueModel = new ScheduleChangeQueue();
        $query = $queueModel->get();
        if ($lecturerIds !== null) {
            $sanitizedIds = array_values(array_filter(array_map('intval', $lecturerIds)));
            if (empty($sanitizedIds)) {
                return 0;
            }
            $query->where(['lecturer_id' => ['in' => $sanitizedIds]]);
        }
        $changes = $query->all();
        if (empty($changes)) {
            return 0;
        }

        $groupedByLecturer = [];
        foreach ($changes as $change) {
            if ($change->lecturer_id) {
                $groupedByLecturer[$change->lecturer_id][] = $change;
            }
        }

        $notifiedCount = 0;
        foreach ($groupedByLecturer as $lecturerId => $lecturerChanges) {
            EventDispatcher::getInstance()->dispatch(
                new ScheduleChangesNotifiedEvent((int)$lecturerId, $lecturerChanges)
            );
            
            // Delete queued items for this lecturer
            $ids = array_map(fn($c) => $c->id, $lecturerChanges);
            if (!empty($ids)) {
                $deleteModel = new ScheduleChangeQueue();
                $deleteModel->get()->where(['id' => ['in' => $ids]])->delete();
            }
            $notifiedCount++;
        }

        $this->logger->info("Değişiklik bildirimleri gönderildi: {$notifiedCount} öğretim elemanına e-posta iletildi.", $this->logContext([
            'notified_count' => $notifiedCount,
            'total_changes' => count($changes)
        ]));

        return $notifiedCount;
    }

    public function recordChange(int $scheduleId, string $actionType, string $detail, ?int $lecturerId = null): void
    {
        $schedule = (new Schedule())->find($scheduleId);
        if ($schedule) {
            (new ScheduleRepository())->touch($scheduleId);

            if ($schedule->is_published) {
                $queue = new ScheduleChangeQueue();
                $queue->fill([
                    'schedule_id' => $scheduleId,
                    'lecturer_id' => $lecturerId,
                    'action_type' => $actionType,
                    'detail' => $detail
                ]);
                $queue->create();
            }
        }
    }
}
