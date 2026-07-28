<?php

namespace App\DTOs;

use App\Enums\ScheduleNoteStatus;

/**
 * Düzenleyici tarafından güncellenen durum ve geri bildirim verisini taşıyan DTO
 */
readonly class ScheduleNoteStatusDTO
{
    public function __construct(
        public int $noteId,
        public ScheduleNoteStatus $status,
        public ?string $editorFeedback = null
    ) {
    }

    public static function fromArray(array $validatedData): self
    {
        return new self(
            noteId: (int)$validatedData['note_id'],
            status: ScheduleNoteStatus::from($validatedData['status']),
            editorFeedback: !empty($validatedData['editor_feedback']) ? trim($validatedData['editor_feedback']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'note_id' => $this->noteId,
            'status' => $this->status->value,
            'editor_feedback' => $this->editorFeedback,
        ];
    }
}
