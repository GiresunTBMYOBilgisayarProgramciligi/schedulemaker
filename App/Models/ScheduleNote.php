<?php

namespace App\Models;

use App\Core\Model;
use App\Enums\ScheduleNoteStatus;
use DateTime;
use Exception;

/**
 * schedule_notes tablosundaki her bir kaydı temsil eden sınıf
 */
class ScheduleNote extends Model
{
    public ?int $id = null;
    public ?int $user_id = null;
    public ?string $academic_year = null;
    public ?string $semester = null;
    public ?string $schedule_type = null;
    public ?string $note = null;
    public ?string $status = 'pending';
    public ?string $editor_feedback = null;
    public ?DateTime $read_at = null;
    public ?int $read_by = null;
    public ?DateTime $status_updated_at = null;
    public ?int $status_updated_by = null;
    public ?DateTime $created_at = null;
    public ?DateTime $updated_at = null;

    public ?User $user = null;
    public ?User $readByUser = null;
    public ?User $statusUpdatedByUser = null;

    protected array $dateFields = ['read_at', 'status_updated_at', 'created_at', 'updated_at'];
    protected array $excludeFromDb = ['user', 'readByUser', 'statusUpdatedByUser'];
    protected string $table_name = "schedule_notes";

    public function getLabel(): string
    {
        return "program notu";
    }

    public function getLogDetail(): string
    {
        return "Not ID: {$this->id} (Kullanıcı ID: {$this->user_id})";
    }

    /**
     * Durum nesnesini Enum olarak döndürür.
     */
    public function getStatusEnum(): ScheduleNoteStatus
    {
        return ScheduleNoteStatus::tryFrom($this->status ?? 'pending') ?? ScheduleNoteStatus::PENDING;
    }

    /**
     * Dizi verisinden model örneği oluşturur ve doldurur.
     */
    public function createFromParams(array $params): self
    {
        $this->fill($params);
        return $this;
    }

    /**
     * Notun ait olduğu kullanıcı (akademisyen) ilişkisini getirir.
     *
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getUserRelation(array $results, array $options = []): array
    {
        $userIds = array_column($results, 'user_id');
        $userIds = array_unique(array_filter($userIds));
        if (empty($userIds)) {
            return $results;
        }

        $query = (new User())->get()->where(['id' => ['in' => $userIds]]);
        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
        $usersKeyed = [];
        foreach ($users as $u) {
            $usersKeyed[$u->id] = $u;
        }

        foreach ($results as &$row) {
            $row['user'] = isset($row['user_id']) && isset($usersKeyed[$row['user_id']])
                ? $usersKeyed[$row['user_id']]
                : null;
        }
        return $results;
    }

    /**
     * Notu okundu olarak işaretleyen kullanıcı (düzenleyici) ilişkisini getirir.
     *
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getReadByUserRelation(array $results, array $options = []): array
    {
        $userIds = array_column($results, 'read_by');
        $userIds = array_unique(array_filter($userIds));
        if (empty($userIds)) {
            return $results;
        }

        $query = (new User())->get()->where(['id' => ['in' => $userIds]]);
        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
        $usersKeyed = [];
        foreach ($users as $u) {
            $usersKeyed[$u->id] = $u;
        }

        foreach ($results as &$row) {
            $row['readByUser'] = isset($row['read_by']) && isset($usersKeyed[$row['read_by']])
                ? $usersKeyed[$row['read_by']]
                : null;
        }
        return $results;
    }

    /**
     * Not durumunu en son güncelleyen kullanıcı (düzenleyici) ilişkisini getirir.
     *
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getStatusUpdatedByUserRelation(array $results, array $options = []): array
    {
        $userIds = array_column($results, 'status_updated_by');
        $userIds = array_unique(array_filter($userIds));
        if (empty($userIds)) {
            return $results;
        }

        $query = (new User())->get()->where(['id' => ['in' => $userIds]]);
        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
        $usersKeyed = [];
        foreach ($users as $u) {
            $usersKeyed[$u->id] = $u;
        }

        foreach ($results as &$row) {
            $row['statusUpdatedByUser'] = isset($row['status_updated_by']) && isset($usersKeyed[$row['status_updated_by']])
                ? $usersKeyed[$row['status_updated_by']]
                : null;
        }
        return $results;
    }
}
