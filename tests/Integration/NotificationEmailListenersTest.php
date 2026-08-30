<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Core\EventDispatcher;
use App\Core\Mailer;
use App\Models\User;
use App\Models\Schedule;
use App\Models\ScheduleNote;
use App\Enums\OwnerType;
use App\Enums\ScheduleNoteStatus;
use App\Events\SchedulePublishedEvent;
use App\Events\ScheduleChangesNotifiedEvent;
use App\Events\ScheduleNoteStatusUpdatedEvent;
use App\Events\ScheduleNoteDeletedEvent;
use App\Events\UserForgotPasswordEvent;
use App\Services\MailQueueService;

class NotificationEmailListenersTest extends BaseTestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = EventDispatcher::getInstance();
    }

    public function testSchedulePublishedEventListenerQueuesEmail(): void
    {
        $lecturerId = $this->insert('users', [
            'name' => 'Ahmet',
            'last_name' => 'Yılmaz',
            'mail' => 'ahmet_' . uniqid() . '@example.com',
            'role' => 'lecturer'
        ]);
        $lecturer = (new User())->find($lecturerId);

        $scheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => OwnerType::USER->value,
            'owner_id' => $lecturerId,
            'semester_no' => 1,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 1
        ]);

        $this->dispatcher->dispatch(new SchedulePublishedEvent($scheduleId));

        // mail_queue tablosunda hocaya ait kuyruk kaydı oluştuğunu doğrula
        $stmt = $this->getDb()->prepare("SELECT * FROM mail_queue WHERE to_email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$lecturer->mail]);
        $queuedMail = $stmt->fetch();

        $this->assertNotEmpty($queuedMail, "Yayınlama bildirimi mail_queue tablosuna eklenmiş olmalı");
        $this->assertEquals($lecturer->mail, $queuedMail['to_email']);
        $this->assertStringContainsString('Programınız Yayınlandı', $queuedMail['subject']);
    }

    public function testScheduleChangesNotifiedEventListenerSendsEmail(): void
    {
        $lecturerId = $this->insert('users', [
            'name' => 'Mehmet',
            'last_name' => 'Demir',
            'mail' => 'mehmet_' . uniqid() . '@example.com',
            'role' => 'lecturer'
        ]);
        $lecturer = (new User())->find($lecturerId);

        $changes = [
            [
                'action' => 'moved',
                'lesson_name' => 'Veritabanı Yönetimi',
                'old_time' => 'Pazartesi 09:00',
                'new_time' => 'Çarşamba 13:00'
            ]
        ];

        $this->dispatcher->dispatch(new ScheduleChangesNotifiedEvent($lecturerId, $changes));

        $this->assertTrue(
            Mailer::hasSent('Ders/Sınav Programınızda Değişiklik Yapıldı', $lecturer->mail),
            'Değişiklik bildirim e-postası yakalanmış olmalıdır'
        );
    }

    public function testScheduleNoteStatusUpdatedEventListenerSendsEmail(): void
    {
        $lecturerId = $this->insert('users', [
            'name' => 'Ayşe',
            'last_name' => 'Kaya',
            'mail' => 'ayse_' . uniqid() . '@example.com',
            'role' => 'lecturer'
        ]);
        $lecturer = (new User())->find($lecturerId);

        $editorId = $this->insert('users', [
            'name' => 'Bölüm',
            'last_name' => 'Başkanı',
            'mail' => 'baskan_' . uniqid() . '@example.com',
            'role' => 'department_head'
        ]);
        $editor = (new User())->find($editorId);

        $noteId = $this->insert('schedule_notes', [
            'user_id' => $lecturerId,
            'academic_year' => '2025 - 2026',
            'semester' => 'Güz',
            'schedule_type' => 'lesson',
            'note' => 'Pazartesi 1. ders olmasın',
            'status' => ScheduleNoteStatus::COMPLETED->value,
            'editor_feedback' => 'Talebiniz kabul edildi ve uygulandı.'
        ]);
        $note = (new ScheduleNote())->find($noteId);

        $this->dispatcher->dispatch(new ScheduleNoteStatusUpdatedEvent($note, $lecturer, $editor));

        $this->assertTrue(
            Mailer::hasSent('Ders Programı İstek Durumu', $lecturer->mail),
            'Not durum güncelleme geri bildirim e-postası yakalanmış olmalıdır'
        );
    }

    public function testScheduleNoteDeletedEventListenerSendsEmail(): void
    {
        $lecturerId = $this->insert('users', [
            'name' => 'Fatma',
            'last_name' => 'Çelik',
            'mail' => 'fatma_' . uniqid() . '@example.com',
            'role' => 'lecturer'
        ]);
        $lecturer = (new User())->find($lecturerId);

        $editorId = $this->insert('users', [
            'name' => 'Bölüm',
            'last_name' => 'Başkanı',
            'mail' => 'baskan_' . uniqid() . '@example.com',
            'role' => 'department_head'
        ]);
        $editor = (new User())->find($editorId);

        $note = new ScheduleNote();
        $note->id = 9999;
        $note->user_id = $lecturerId;
        $note->academic_year = '2025 - 2026';
        $note->semester = 'Güz';
        $note->schedule_type = 'lesson';
        $note->note = 'Silinecek not metni';

        $this->dispatcher->dispatch(new ScheduleNoteDeletedEvent($note, $lecturer, $editor));

        $this->assertTrue(
            Mailer::hasSent('Ders Programı Notunuz Silindi', $lecturer->mail),
            'Not silinme e-postası yakalanmış olmalıdır'
        );
    }

    public function testUserForgotPasswordEventListenerSendsEmail(): void
    {
        $userId = $this->insert('users', [
            'name' => 'Can',
            'last_name' => 'Yıldız',
            'mail' => 'can_' . uniqid() . '@example.com',
            'role' => 'lecturer'
        ]);
        $user = (new User())->find($userId);
        $token = 'test_token_' . bin2hex(random_bytes(8));

        $this->dispatcher->dispatch(new UserForgotPasswordEvent($user, $token));

        $this->assertTrue(
            Mailer::hasSent('Şifre Sıfırlama İsteği', $user->mail),
            'Şifre sıfırlama bağlantı e-postası yakalanmış olmalıdır'
        );
    }
}
