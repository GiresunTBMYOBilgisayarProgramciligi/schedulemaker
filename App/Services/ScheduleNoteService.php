<?php

namespace App\Services;

use App\Repositories\ScheduleNoteRepository;
use App\Repositories\UserRepository;
use App\DTOs\ScheduleNoteDTO;
use App\DTOs\ScheduleNoteStatusDTO;
use App\Models\ScheduleNote;
use App\Models\User;
use App\Events\ScheduleNoteStatusUpdatedEvent;
use App\Events\ScheduleNoteDeletedEvent;
use App\Core\EventDispatcher;

class ScheduleNoteService extends BaseService
{
    private ScheduleNoteRepository $repository;
    private UserRepository $userRepository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ScheduleNoteRepository();
        $this->userRepository = new UserRepository();
    }

    /**
     * Not ekler veya günceller.
     */
    public function saveNote(ScheduleNoteDTO $dto): ScheduleNote
    {
        $note = $this->repository->saveOrUpdate($dto);
        $this->logger->info("Hoca notu kaydedildi", $this->logContext(['note_id' => $note->id, 'user_id' => $dto->userId]));
        return $note;
    }

    /**
     * Akademisyenin kendi notlarını getirir.
     */
    public function getMyNotes(int $userId): array
    {
        return $this->repository->getNotesByUser($userId);
    }

    /**
     * Bir yöneticinin başka bir akademisyenin notlarını incelemesi (okundu olarak da işaretler).
     */
    public function getNotesForUserByEditor(int $userId, User $editor): array
    {
        $notes = $this->repository->getNotesByUser($userId);
        foreach ($notes as $note) {
            /** @var ScheduleNote $note */
            $this->repository->markAsRead($note->id, $editor->id);
        }
        return $notes;
    }

    /**
     * Program düzenleyici için programa özel hoca notlarını getirir.
     */
    public function getProgramNotes(int $programId, string $academicYear, string $semester, string $scheduleType, User $editor, bool $markAsRead = true): array
    {
        $notes = $this->repository->getProgramNotes($programId, $academicYear, $semester, $scheduleType);

        if ($markAsRead) {
            foreach ($notes as $note) {
                /** @var ScheduleNote $note */
                $this->repository->markAsRead($note->id, $editor->id);
            }
        }

        return $notes;
    }

    /**
     * Program düzenleyici için seçili akademisyene ait notları getirir.
     */
    public function getLecturerNotes(int $userId, string $academicYear, string $semester, string $scheduleType, User $editor, bool $markAsRead = true): array
    {
        $notes = $this->repository->getLecturerNotes($userId, $academicYear, $semester, $scheduleType);

        if ($markAsRead) {
            foreach ($notes as $note) {
                /** @var ScheduleNote $note */
                $this->repository->markAsRead($note->id, $editor->id);
            }
        }

        return $notes;
    }

    /**
     * Düzenleyici bir notu okundu işaretler.
     */
    public function markAsRead(int $noteId, User $editor): bool
    {
        return $this->repository->markAsRead($noteId, $editor->id);
    }

    /**
     * Düzenleyici durum günceller ve akademisyene bildirim e-postası fırlatır.
     */
    public function updateStatus(ScheduleNoteStatusDTO $dto, User $editor): ?ScheduleNote
    {
        $updated = $this->repository->updateStatus($dto, $editor->id);

        if ($updated) {
            /** @var ScheduleNote|null $note */
            $note = $this->repository->find($dto->noteId);
            if ($note) {
                /** @var User|null $lecturer */
                $lecturer = $this->userRepository->find($note->user_id);
                if ($lecturer) {
                    // Event Fırlat
                    EventDispatcher::getInstance()->dispatch(
                        new ScheduleNoteStatusUpdatedEvent($note, $lecturer, $editor)
                    );
                }
            }
            $this->logger->info("Hoca notu durumu güncellendi", $this->logContext([
                'note_id' => $dto->noteId,
                'status' => $dto->status->value,
                'editor_id' => $editor->id
            ]));

            return $note;
        }

        return null;
    }

    /**
     * Notu siler ve ilgili akademisyene e-posta bildirimi gönderir.
     */
    public function deleteNote(int $noteId, User $user): bool
    {
        /** @var ScheduleNote|null $note */
        $note = $this->repository->find($noteId);
        if (!$note) {
            return false;
        }

        /** @var User|null $lecturer */
        $lecturer = $this->userRepository->find($note->user_id);

        $deleted = $this->repository->delete($noteId);
        if ($deleted) {
            $this->logger->info("Hoca notu silindi", $this->logContext([
                'note_id' => $noteId,
                'deleted_by' => $user->id
            ]));

            if ($lecturer) {
                EventDispatcher::getInstance()->dispatch(
                    new ScheduleNoteDeletedEvent($note, $lecturer, $user)
                );
            }
        }
        return $deleted;
    }
}
