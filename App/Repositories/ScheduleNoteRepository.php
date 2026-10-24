<?php

namespace App\Repositories;

use App\Models\ScheduleNote;
use App\Models\User;
use App\Models\LessonAssignment;
use App\Models\Lesson;
use App\DTOs\ScheduleNoteDTO;
use App\DTOs\ScheduleNoteStatusDTO;
use App\Enums\ScheduleNoteStatus;

class ScheduleNoteRepository extends BaseRepository
{
    protected string $modelClass = ScheduleNote::class;

    /**
     * Kullanıcı ve bağlama göre var olan notu bulur.
     */
    public function getByUserAndContext(int $userId, string $academicYear, string $semester, string $scheduleType): ?ScheduleNote
    {
        /** @var ScheduleNote|null $note */
        $note = (new ScheduleNote())->get()
            ->where([
                'user_id' => $userId,
                'academic_year' => $academicYear,
                'semester' => $semester,
                'schedule_type' => $scheduleType,
            ])
            ->first();

        return $note;
    }

    /**
     * Kullanıcının tüm notlarını ilişkili yetkili verileriyle getirir.
     */
    public function getNotesByUser(int $userId): array
    {
        return (new ScheduleNote())->get()
            ->where(['user_id' => $userId])
            ->orderBy('updated_at', 'DESC')
            ->with(['readByUser', 'statusUpdatedByUser'])
            ->all();
    }

    /**
     * Belirtilen programa ders veren veya programın bağlı olduğu akademisyenlerin seçili döneme ait tüm notlarını getirir.
     */
    public function getProgramNotes(int $programId, string $academicYear, string $semester, string $scheduleType): array
    {
        // 1. Programın derslerine atanan akademisyenlerin ve programdaki kullanıcıların ID'leri
        $stmt = $this->db->prepare("
            SELECT DISTINCT la.lecturer_id 
            FROM lesson_assignments la
            JOIN lessons l ON l.id = la.lesson_id
            WHERE l.program_id = :program_id
              AND la.academic_year = :academic_year
              AND la.semester = :semester
              AND la.lecturer_id IS NOT NULL
        ");
        $stmt->execute([
            'program_id'    => $programId,
            'academic_year' => $academicYear,
            'semester'      => $semester,
        ]);
        $lecturerIds = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        $users = (new User())->get()->where(['program_id' => $programId])->all();
        foreach ($users as $u) {
            $lecturerIds[] = (int)$u->id;
        }

        $lecturerIds = array_values(array_unique(array_filter($lecturerIds)));

        if (empty($lecturerIds)) {
            return [];
        }

        return (new ScheduleNote())->get()
            ->where([
                'user_id' => ['in' => $lecturerIds],
                'academic_year' => $academicYear,
                'semester' => $semester,
                'schedule_type' => $scheduleType,
            ])
            ->orderBy('updated_at', 'DESC')
            ->with(['user', 'readByUser'])
            ->all();
    }

    /**
     * Hocanın bulunduğu program ID'lerini döndürür (kadro, affiliation ve ders atamaları).
     *
     * @return int[]
     */
    public function getLecturerProgramIds(int $lecturerId): array
    {
        $stmt = $this->db->prepare("
            SELECT program_id FROM users WHERE id = :uid AND program_id IS NOT NULL
            UNION
            SELECT program_id FROM user_affiliations WHERE user_id = :uid2 AND program_id IS NOT NULL
            UNION
            SELECT l.program_id FROM lesson_assignments la
            JOIN lessons l ON l.id = la.lesson_id
            WHERE la.lecturer_id = :uid3 AND l.program_id IS NOT NULL
        ");
        $stmt->execute(['uid' => $lecturerId, 'uid2' => $lecturerId, 'uid3' => $lecturerId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Birimdeki tüm hocaların (kadro, affiliation veya birim derslerine atanmış) seçili döneme ait notlarını getirir.
     */
    public function getUnitNotes(int $unitId, string $academicYear, string $semester, string $scheduleType): array
    {
        $stmt = $this->db->prepare("
            SELECT id FROM users WHERE unit_id = :unit1
            UNION
            SELECT user_id FROM user_affiliations WHERE unit_id = :unit2
            UNION
            SELECT la.lecturer_id
            FROM lesson_assignments la
            JOIN lessons l ON l.id = la.lesson_id
            LEFT JOIN programs p ON p.id = l.program_id
            JOIN departments d ON d.id = COALESCE(l.department_id, p.department_id)
            WHERE d.unit_id = :unit3
              AND la.academic_year = :academic_year
              AND la.semester = :semester
              AND la.lecturer_id IS NOT NULL
        ");
        $stmt->execute([
            'unit1'         => $unitId,
            'unit2'         => $unitId,
            'unit3'         => $unitId,
            'academic_year' => $academicYear,
            'semester'      => $semester,
        ]);
        $lecturerIds = array_values(array_unique(array_filter(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)))));

        if (empty($lecturerIds)) {
            return [];
        }

        return (new ScheduleNote())->get()
            ->where([
                'user_id' => ['in' => $lecturerIds],
                'academic_year' => $academicYear,
                'semester' => $semester,
                'schedule_type' => $scheduleType,
            ])
            ->orderBy('updated_at', 'DESC')
            ->with(['user', 'readByUser'])
            ->all();
    }

    /**
     * Belirtilen akademisyenin seçili döneme ait notlarını getirir.
     */
    public function getLecturerNotes(int $userId, string $academicYear, string $semester, string $scheduleType): array
    {
        return (new ScheduleNote())->get()
            ->where([
                'user_id' => $userId,
                'academic_year' => $academicYear,
                'semester' => $semester,
                'schedule_type' => $scheduleType,
            ])
            ->orderBy('updated_at', 'DESC')
            ->with(['user', 'readByUser'])
            ->all();
    }

    /**
     * Not ekler veya var olan notu günceller.
     */
    public function saveOrUpdate(ScheduleNoteDTO $dto): ScheduleNote
    {
        $existing = $this->getByUserAndContext($dto->userId, $dto->academicYear, $dto->semester, $dto->scheduleType);

        if ($existing) {
            $existing->note = $dto->note;
            $existing->status = ScheduleNoteStatus::PENDING->value;
            $existing->editor_feedback = null;
            $existing->read_at = null;
            $existing->read_by = null;
            $existing->status_updated_at = null;
            $existing->status_updated_by = null;
            $existing->updated_at = new \DateTime();
            $existing->update();

            /** @var ScheduleNote $updated */
            $updated = $this->find($existing->id);
            return $updated;
        }

        $note = new ScheduleNote();
        $note->user_id = $dto->userId;
        $note->academic_year = $dto->academicYear;
        $note->semester = $dto->semester;
        $note->schedule_type = $dto->scheduleType;
        $note->note = $dto->note;
        $note->status = ScheduleNoteStatus::PENDING->value;
        $note->create();

        /** @var ScheduleNote $created */
        $created = $this->find($note->id);
        return $created;
    }

    /**
     * Notu 'Görüldü' (read) olarak işaretler (Eğer henüz görülmediyse).
     */
    public function markAsRead(int $noteId, int $editorId): bool
    {
        /** @var ScheduleNote|null $note */
        $note = $this->find($noteId);
        if (!$note) {
            return false;
        }

        if ($note->status === ScheduleNoteStatus::PENDING->value) {
            $note->status = ScheduleNoteStatus::READ->value;
        }
        if ($note->read_at === null) {
            $note->read_at = new \DateTime();
        }
        if ($note->read_by === null) {
            $note->read_by = $editorId;
        }

        return $note->update();
    }

    /**
     * Düzenleyici durum güncellemesini ve açıklamasını kaydeder.
     */
    public function updateStatus(ScheduleNoteStatusDTO $dto, int $editorId): bool
    {
        /** @var ScheduleNote|null $note */
        $note = $this->find($dto->noteId);
        if (!$note) {
            return false;
        }

        $note->status = $dto->status->value;
        $note->editor_feedback = $dto->editorFeedback;
        $note->status_updated_at = new \DateTime();
        $note->status_updated_by = $editorId;
        if ($note->read_at === null) {
            $note->read_at = new \DateTime();
        }
        if ($note->read_by === null) {
            $note->read_by = $editorId;
        }

        return $note->update();
    }

    /**
     * Notu siler.
     */
    public function delete(int $id): bool
    {
        /** @var ScheduleNote|null $note */
        $note = $this->find($id);
        if (!$note) {
            return false;
        }
        return $note->delete();
    }

    /**
     * Birden fazla notu tek bir SQL sorgusuyla 'Görüldü' (read) olarak işaretler (Toplu güncelleme).
     *
     * @param int[] $noteIds
     * @param int $editorId
     * @return int Güncellenen kayıt sayısı
     */
    public function markMultipleAsRead(array $noteIds, int $editorId): int
    {
        $validIds = array_values(array_unique(array_filter(array_map('intval', $noteIds))));
        if (empty($validIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $sql = "UPDATE schedule_notes 
                SET status = CASE WHEN status = 'pending' THEN 'read' ELSE status END,
                    read_at = COALESCE(read_at, NOW()),
                    read_by = COALESCE(read_by, ?)
                WHERE id IN ($placeholders) 
                  AND (read_at IS NULL OR read_by IS NULL OR status = 'pending')";

        $stmt = $this->db->prepare($sql);
        $params = array_merge([$editorId], $validIds);
        $stmt->execute($params);

        return $stmt->rowCount();
    }
}
