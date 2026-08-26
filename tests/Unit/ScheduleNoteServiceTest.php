<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\DTOs\ScheduleNoteDTO;
use App\DTOs\ScheduleNoteStatusDTO;
use App\Enums\ScheduleNoteStatus;
use App\Services\ScheduleNoteService;
use App\Models\User;
use App\Models\ScheduleNote;

class ScheduleNoteServiceTest extends BaseTestCase
{
    private ScheduleNoteService $service;
    private int $userId;
    private int $editorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScheduleNoteService();

        // Test kullanıcısı oluştur
        $this->userId = $this->insert('users', [
            'name' => 'Test',
            'last_name' => 'Hoca',
            'mail' => 'testhoca_' . uniqid() . '@example.com',
            'role' => 'lecturer'
        ]);

        $this->editorId = $this->insert('users', [
            'name' => 'Test',
            'last_name' => 'Bölüm Başkanı',
            'mail' => 'testbaskan_' . uniqid() . '@example.com',
            'role' => 'department_head'
        ]);
    }

    public function testSaveNoteCreatesAndUpdates(): void
    {
        $dto = new ScheduleNoteDTO(
            userId: $this->userId,
            academicYear: '2025 - 2026',
            semester: 'Güz',
            scheduleType: 'lesson',
            note: 'Salı öğleden sonra ders olmasın'
        );

        $note = $this->service->saveNote($dto);

        $this->assertNotNull($note);
        $this->assertEquals('Salı öğleden sonra ders olmasın', $note->note);
        $this->assertEquals('pending', $note->status);

        // Not güncelleme
        $updatedDto = new ScheduleNoteDTO(
            userId: $this->userId,
            academicYear: '2025 - 2026',
            semester: 'Güz',
            scheduleType: 'lesson',
            note: 'Salı tam gün ders olmasın'
        );

        $updatedNote = $this->service->saveNote($updatedDto);
        $this->assertEquals($note->id, $updatedNote->id);
        $this->assertEquals('Salı tam gün ders olmasın', $updatedNote->note);
    }

    public function testUpdateStatus(): void
    {
        $dto = new ScheduleNoteDTO(
            userId: $this->userId,
            academicYear: '2025 - 2026',
            semester: 'Güz',
            scheduleType: 'lesson',
            note: 'Cuma 1. ders olmasın'
        );

        $note = $this->service->saveNote($dto);

        $editor = new User();
        $editor->id = $this->editorId;
        $editor->name = 'Bölüm';
        $editor->last_name = 'Başkanı';

        $statusDto = new ScheduleNoteStatusDTO(
            noteId: $note->id,
            status: ScheduleNoteStatus::COMPLETED,
            editorFeedback: 'Cuma dersiniz 3. derse alınmıştır.'
        );

        $result = $this->service->updateStatus($statusDto, $editor);
        $this->assertNotNull($result);
        $this->assertInstanceOf(ScheduleNote::class, $result);

        $myNotes = $this->service->getMyNotes($this->userId);
        $this->assertCount(1, $myNotes);
        $this->assertEquals('completed', $myNotes[0]->status);
        $this->assertEquals('Cuma dersiniz 3. derse alınmıştır.', $myNotes[0]->editor_feedback);
    }

    public function testModelRelations(): void
    {
        $dto = new ScheduleNoteDTO(
            userId: $this->userId,
            academicYear: '2025 - 2026',
            semester: 'Güz',
            scheduleType: 'lesson',
            note: 'İlişki testi notu'
        );

        $note = $this->service->saveNote($dto);

        $editor = new User();
        $editor->id = $this->editorId;
        $editor->name = 'Bölüm';
        $editor->last_name = 'Başkanı';

        $statusDto = new ScheduleNoteStatusDTO(
            noteId: $note->id,
            status: ScheduleNoteStatus::COMPLETED,
            editorFeedback: 'Onaylandı.'
        );

        $this->service->updateStatus($statusDto, $editor);

        $noteModel = new ScheduleNote();
        $notes = $noteModel->get()->where(['id' => $note->id])->with(['user', 'readByUser', 'statusUpdatedByUser'])->all();

        $this->assertCount(1, $notes);
        $fetched = $notes[0];
        $this->assertNotNull($fetched->user);
        $this->assertEquals($this->userId, $fetched->user->id);
        $this->assertNotNull($fetched->readByUser);
        $this->assertEquals($this->editorId, $fetched->readByUser->id);
        $this->assertNotNull($fetched->statusUpdatedByUser);
        $this->assertEquals($this->editorId, $fetched->statusUpdatedByUser->id);
    }

    public function testGetLecturerNotes(): void
    {
        $dto = new ScheduleNoteDTO(
            userId: $this->userId,
            academicYear: '2026 - 2027',
            semester: 'Güz',
            scheduleType: 'lesson',
            note: 'Hoca bazlı not testi'
        );

        $this->service->saveNote($dto);

        $editor = new User();
        $editor->id = $this->editorId;

        $notes = $this->service->getLecturerNotes($this->userId, '2026 - 2027', 'Güz', 'lesson', $editor);
        $this->assertCount(1, $notes);
        $this->assertEquals('Hoca bazlı not testi', $notes[0]->note);
        $this->assertEquals($this->userId, $notes[0]->user->id);
    }

    public function testDeleteNote(): void
    {
        $dto = new ScheduleNoteDTO(
            userId: $this->userId,
            academicYear: '2026 - 2027',
            semester: 'Bahar',
            scheduleType: 'lesson',
            note: 'Silinecek not'
        );

        $note = $this->service->saveNote($dto);

        $user = new User();
        $user->id = $this->userId;

        $deleted = $this->service->deleteNote($note->id, $user);
        $this->assertTrue($deleted);

        $myNotes = $this->service->getMyNotes($this->userId);
        $found = array_filter($myNotes, fn($n) => $n->id === $note->id);
        $this->assertEmpty($found);
    }
}
