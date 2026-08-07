<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Gate;
use App\Middlewares\AuthMiddleware;
use App\Models\ScheduleNote;
use App\Validators\ScheduleNoteValidator;
use App\Services\ScheduleNoteService;
use App\Repositories\ScheduleNoteRepository;
use Exception;

class ScheduleNoteController extends Controller
{
    private ScheduleNoteService $service;

    public function __construct()
    {
        $this->service = new ScheduleNoteService();
    }

    /**
     * Akademisyenin not eklemesi / güncellemesi (AJAX)
     */
    public function saveNote(array $requestData): array
    {
        $currentUser = AuthMiddleware::user();
        if (!$currentUser) {
            throw new Exception("Oturum açmanız gerekmektedir.");
        }

        if (empty($requestData['user_id'])) {
            $requestData['user_id'] = $currentUser->id;
        }

        $validator = new ScheduleNoteValidator();
        $dto = $validator->getNoteDTO($requestData);

        Gate::authorize('create', ScheduleNote::class, "Not ekleme yetkiniz yok.", $dto);

        $note = $this->service->saveNote($dto);

        return [
            "status" => "success",
            "msg" => "Program notu başarıyla kaydedildi.",
            "data" => [
                "id" => $note->id,
                "note" => $note->note,
                "status_label" => $note->getStatusEnum()->getLabel(),
                "badge_class" => $note->getStatusEnum()->getBadgeClass(),
                "updated_at" => $note->updated_at?->format('d.m.Y H:i')
            ]
        ];
    }

    /**
     * Akademisyenin veya yöneticinin profildeki notları getirmesi (AJAX)
     */
    public function getMyNotes(array $requestData = []): array
    {
        $currentUser = AuthMiddleware::user();
        if (!$currentUser) {
            throw new Exception("Oturum açmanız gerekmektedir.");
        }

        $targetUserId = !empty($requestData['user_id']) ? (int)$requestData['user_id'] : $currentUser->id;

        Gate::authorize('view', ScheduleNote::class, "Bu kullanıcının notlarını görme yetkiniz yok.", $targetUserId);

        if ($targetUserId !== $currentUser->id && Gate::check('canManageNotes', ScheduleNote::class)) {
            $notes = $this->service->getNotesForUserByEditor($targetUserId, $currentUser);
        } else {
            $notes = $this->service->getMyNotes($targetUserId);
        }

        $data = array_map(function (ScheduleNote $note) {
            return [
                'id' => $note->id,
                'academic_year' => $note->academic_year,
                'semester' => $note->semester,
                'schedule_type' => $note->schedule_type,
                'note' => $note->note,
                'status' => $note->status,
                'status_label' => $note->getStatusEnum()->getLabel(),
                'badge_class' => $note->getStatusEnum()->getBadgeClass(),
                'editor_feedback' => $note->editor_feedback,
                'read_at' => $note->read_at?->format('d.m.Y H:i'),
                'read_by_name' => $note->readByUser?->getFullName(),
                'status_updated_at' => $note->status_updated_at?->format('d.m.Y H:i'),
                'status_updated_by_name' => $note->statusUpdatedByUser?->getFullName(),
                'updated_at' => $note->updated_at?->format('d.m.Y H:i'),
            ];
        }, $notes);

        return [
            "status" => "success",
            "data" => $data
        ];
    }

    /**
     * Program düzenleyici için programa ait hoca notlarını getirir (AJAX)
     */
    public function getProgramNotes(array $requestData): array
    {
        $currentUser = AuthMiddleware::user();
        if (!$currentUser) {
            throw new Exception("Oturum açmanız gerekmektedir.");
        }

        $programId = !empty($requestData['program_id']) ? (int)$requestData['program_id'] : 0;
        $lecturerId = !empty($requestData['lecturer_id']) ? (int)$requestData['lecturer_id'] : 0;
        $academicYear = $requestData['academic_year'] ?? '';
        $semester = $requestData['semester'] ?? '';
        $scheduleType = $requestData['schedule_type'] ?? '';

        if (empty($academicYear) || empty($semester) || empty($scheduleType)) {
            throw new Exception("Eksik arama parametreleri.");
        }

        if ($programId <= 0 && $lecturerId <= 0) {
            throw new Exception("Lütfen önce bir program veya hoca seçiniz.");
        }

        $markAsRead = !isset($requestData['mark_read']) || ($requestData['mark_read'] !== '0' && $requestData['mark_read'] !== 0 && $requestData['mark_read'] !== false);

        Gate::authorize('canManageNotes', ScheduleNote::class, "Program notlarını görme yetkiniz yok.");

        if ($lecturerId > 0 && $programId <= 0) {
            $notes = $this->service->getLecturerNotes($lecturerId, $academicYear, $semester, $scheduleType, $currentUser, $markAsRead);
        } else {
            $notes = $this->service->getProgramNotes($programId, $academicYear, $semester, $scheduleType, $currentUser, $markAsRead);
        }

        $data = array_map(function (ScheduleNote $note) {
            return [
                'id' => $note->id,
                'user_id' => $note->user_id,
                'lecturer_name' => $note->user ? $note->user->getFullName() : 'Bilinmeyen Akademisyen',
                'lecturer_title' => $note->user?->title ?? '',
                'academic_year' => $note->academic_year,
                'semester' => $note->semester,
                'schedule_type' => $note->schedule_type,
                'note' => $note->note,
                'status' => $note->status,
                'status_label' => $note->getStatusEnum()->getLabel(),
                'badge_class' => $note->getStatusEnum()->getBadgeClass(),
                'editor_feedback' => $note->editor_feedback,
                'read_at' => $note->read_at?->format('d.m.Y H:i'),
                'read_by' => $note->read_by,
                'read_by_name' => $note->readByUser?->getFullName(),
                'updated_at' => $note->updated_at?->format('d.m.Y H:i'),
            ];
        }, $notes);

        return [
            "status" => "success",
            "data" => $data
        ];
    }

    /**
     * Düzenleyicinin not durumunu güncellemesi (AJAX)
     */
    public function updateStatus(array $requestData): array
    {
        $currentUser = AuthMiddleware::user();
        if (!$currentUser) {
            throw new Exception("Oturum açmanız gerekmektedir.");
        }

        Gate::authorize('canManageNotes', ScheduleNote::class, "Not durumunu güncelleme yetkiniz yok.");

        $validator = new ScheduleNoteValidator();
        $dto = $validator->getStatusDTO($requestData);

        $this->service->updateStatus($dto, $currentUser);

        return [
            "status" => "success",
            "msg" => "İstek durumu ve geri bildirim başarıyla güncellendi."
        ];
    }

    /**
     * Notu siler (AJAX)
     */
    public function deleteNote(array $requestData): array
    {
        $currentUser = AuthMiddleware::user();
        if (!$currentUser) {
            throw new Exception("Oturum açmanız gerekmektedir.");
        }

        $noteId = !empty($requestData['note_id']) ? (int)$requestData['note_id'] : 0;
        if ($noteId <= 0) {
            throw new Exception("Geçersiz not ID'si.");
        }

        /** @var ScheduleNote|null $note */
        $note = (new ScheduleNoteRepository())->find($noteId);
        if (!$note) {
            throw new Exception("Not bulunamadı.");
        }

        Gate::authorize('delete', $note, "Bu notu silme yetkiniz bulunmamaktadır.");

        $success = $this->service->deleteNote($noteId, $currentUser);

        if (!$success) {
            throw new Exception("Not silinemedi.");
        }

        return [
            "status" => "success",
            "msg" => "Program notu başarıyla silindi."
        ];
    }
}
