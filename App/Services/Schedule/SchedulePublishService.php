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
use App\Models\UserAffiliation;
use App\Enums\OwnerType;
use App\Core\Gate;
use App\Enums\PermissionType;
use App\Models\User;
use App\Mailers\ScheduleMailer;
use Exception;
use function App\Helpers\getSettingValue;

class SchedulePublishService extends BaseService
{
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

        $schedule->is_published = !$schedule->is_published;
        $schedule->published_at = $schedule->is_published ? date('Y-m-d H:i:s') : null;
        $schedule->update();

        if ($schedule->is_published) {
            EventDispatcher::getInstance()->dispatch(new SchedulePublishedEvent($schedule->id));
        }

        $actionText = $schedule->is_published ? "yayınlandı" : "yayından kaldırıldı";
        $screenName = $schedule->getScheduleScreenName();
        $this->logger->info("Program ($screenName) {$actionText}.", $this->logContext([
            'schedule_id' => $schedule->id,
            'is_published' => $schedule->is_published
        ]));

        return [
            "status" => "success",
            "msg" => "Program " . $actionText,
            "is_published" => $schedule->is_published
        ];
    }

    /**
     * @throws Exception
     */
    public function bulkPublish(?string $semester = null, ?string $academicYear = null, bool $publishStatus = true, ?string $type = null): int
    {
        $semester = $semester ?? getSettingValue('semester');
        $academicYear = $academicYear ?? getSettingValue('academic_year');

        $where = [
            'semester' => $semester,
            'academic_year' => $academicYear,
            'owner_type' => ['!=' => OwnerType::LESSON->value]
        ];
        
        if ($type) {
            $where['type'] = $type;
        }

        $schedules = (new Schedule())->get()->where($where)->all();

        $count = 0;
        foreach ($schedules as $schedule) {
            if ((bool)$schedule->is_published !== $publishStatus) {
                $schedule->is_published = $publishStatus ? 1 : 0;
                $schedule->published_at = $publishStatus ? date('Y-m-d H:i:s') : null;
                $schedule->update();
                $count++;

                if ($publishStatus) {
                    EventDispatcher::getInstance()->dispatch(new SchedulePublishedEvent($schedule->id));
                }
            }
        }

        $actionText = $publishStatus ? "yayınlandı" : "yayından kaldırıldı";
        $this->logger->info("Toplu program işlemi: {$count} adet program {$actionText} ($academicYear - $semester).", $this->logContext([
            'count' => $count,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'publish_status' => $publishStatus
        ]));

        return $count;
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
     * @return array ['program' => [ids], 'lesson' => [ids], 'classroom' => [ids], 'user' => [ids], 'cross_unit_users' => [user_id => [lesson_ids]]]
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
            'cross_unit_users' => []
        ];

        $departmentIds = [];
        $programIds = [];
        $lessonIds = [];
        $classroomIds = [];
        $userIds = [];
        $crossUnitUsers = [];

        // 1. İlgili Entity ID'lerini Topla
        if ($scope === 'unit' || $scope === 'department') {
            $departmentIds = ($scope === 'department')
                ? [$scopeId]
                : array_map(fn($d) => $d->id, (new Department())->get()->where(['unit_id' => $scopeId])->all());

            if (!empty($departmentIds)) {
                $programs = (new Program())->get()->where(['department_id' => ['in' => $departmentIds]])->all();
                $programIds = array_map(fn($p) => $p->id, $programs);

                $lessons = (new Lesson())->get()->where(['department_id' => ['in' => $departmentIds]])->all();
                $lessonIds = array_map(fn($l) => $l->id, $lessons);
            }

            if ($scope === 'unit') {
                $buildings = (new Building())->get()->where(['unit_id' => $scopeId])->all();
                $buildingIds = array_map(fn($b) => $b->id, $buildings);
                if (!empty($buildingIds)) {
                    $classrooms = (new Classroom())->get()->where(['building_id' => ['in' => $buildingIds]])->all();
                    $classroomIds = array_map(fn($c) => $c->id, $classrooms);
                }

                $affiliations = (new UserAffiliation())->get()->where(['unit_id' => $scopeId])->all();
            } else {
                $affiliations = (new UserAffiliation())->get()->where(['department_id' => $scopeId])->all();
            }
            $userIds = array_map(fn($a) => $a->user_id, $affiliations);

        } elseif ($scope === 'program') {
            $programIds = [$scopeId];

            $lessons = (new Lesson())->get()->where(['program_id' => $scopeId])->all();
            $lessonIds = array_map(fn($l) => $l->id, $lessons);

            if (!empty($lessonIds)) {
                $assignments = (new LessonAssignment())->get()->where([
                    'lesson_id' => ['in' => $lessonIds],
                    'semester' => $semester,
                    'academic_year' => $academicYear
                ])->all();
                $lecturerIds = array_unique(array_filter(array_map(fn($a) => $a->lecturer_id, $assignments)));

                if (!empty($lecturerIds)) {
                    $program = (new Program())->find($scopeId);
                    if ($program) {
                        $department = (new Department())->find($program->department_id);
                        $unitId = $department ? $department->unit_id : null;

                        foreach ($lecturerIds as $lecturerId) {
                            $affiliations = (new UserAffiliation())->get()->where(['user_id' => $lecturerId])->all();
                            $isAffiliated = false;
                            foreach ($affiliations as $aff) {
                                if ($aff->program_id == $scopeId || $aff->department_id == $program->department_id || $aff->unit_id == $unitId) {
                                    $isAffiliated = true;
                                    break;
                                }
                            }
                            if ($isAffiliated) {
                                $userIds[] = $lecturerId;
                            }
                        }
                    }
                }
            }
        }
        
        $userIds = array_unique($userIds);

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

        // 3. Farklı Birim Hocalarını Bul
        // Yayınlanacak derslere (lessonIds) giren ama userIds içinde OLMAYAN hocalar
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
        $scheduleIdsData = $this->collectScheduleIdsByScope($scope, $scopeId, $semester, $academicYear, $type);
        $scheduleIdsToProcess = $this->filterScheduleIdsByTab($scheduleIdsData, $ownerTypeTab);

        if (empty($scheduleIdsToProcess)) {
            return ['count' => 0, 'notified_users' => 0, 'cross_unit_notified' => 0];
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
                $schedule->is_published = $publishStatus ? 1 : 0;
                $schedule->published_at = $publishStatus ? date('Y-m-d H:i:s') : null;
                $schedule->update();
                $count++;

                if ($publishStatus) {
                    EventDispatcher::getInstance()->dispatch(new SchedulePublishedEvent($schedule->id));
                    if ($schedule->owner_type === OwnerType::USER->value) {
                        $notifiedUsers++;
                    }
                }
            }
        }

        $crossUnitNotified = 0;
        if ($publishStatus && $ownerTypeTab === null) {
            $crossUnitNotified = $this->notifyCrossUnitUsers($scheduleIdsData['cross_unit_users'], $scope, $scopeId, $semester, $academicYear, $type);
        }

        $actionText = $publishStatus ? "yayınlandı" : "yayından kaldırıldı";
        $this->logger->info("Toplu program işlemi ($scope:$scopeId): {$count} adet program {$actionText}.", $this->logContext([
            'count' => $count,
            'scope' => $scope,
            'scope_id' => $scopeId,
            'publish_status' => $publishStatus
        ]));

        return [
            'count' => $count,
            'notified_users' => $notifiedUsers,
            'cross_unit_notified' => $crossUnitNotified
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

        $unitName = "";
        if ($scope === 'unit') {
            $unitName = (new Unit())->find($scopeId)?->name ?? 'Birim';
        } elseif ($scope === 'department') {
            $unitName = (new Department())->find($scopeId)?->name ?? 'Bölüm';
        } elseif ($scope === 'program') {
            $unitName = (new Program())->find($scopeId)?->name ?? 'Program';
        }

        $scheduleModel = new Schedule();
        $scheduleModel->type = $type;
        $typeLabel = $scheduleModel->getScheduleTypeName();

        $mailer = new ScheduleMailer();

        foreach ($crossUnitUsers as $lecturerId => $lessonIds) {
            $lecturer = (new User())->find($lecturerId);
            if ($lecturer && !empty($lecturer->mail)) {
                $sent = $mailer->sendCrossUnitNotification($lecturer, $unitName, $typeLabel, $semester, $academicYear);
                if ($sent) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Sekme (Tab) seçimine göre işlenecek schedule ID'lerini filtreler
     */
    private function filterScheduleIdsByTab(array $scheduleIdsData, ?string $ownerTypeTab): array
    {
        if ($ownerTypeTab === null) {
            return array_merge(
                $scheduleIdsData[OwnerType::PROGRAM->value] ?? [],
                $scheduleIdsData[OwnerType::CLASSROOM->value] ?? [],
                $scheduleIdsData[OwnerType::USER->value] ?? []
            );
        }
        return $scheduleIdsData[$ownerTypeTab] ?? [];
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
        $scheduleIdsToProcess = $this->filterScheduleIdsByTab($scheduleIdsData, $ownerTypeTab);

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
     * @throws Exception
     */
    public function notifyChanges(): int
    {
        $changes = (new ScheduleChangeQueue())->get()->all();
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
                $queueModel = new ScheduleChangeQueue();
                $queueModel->get()->where(['id' => ['in' => $ids]])->delete();
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
            $schedule->updated_at = date('Y-m-d H:i:s');
            $schedule->update();

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
