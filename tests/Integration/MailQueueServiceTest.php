<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\MailQueueService;
use App\Models\MailQueue;
use App\Enums\MailQueueStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Enums\OwnerType;
use App\Events\SchedulePublishedEvent;
use App\Listeners\SendSchedulePublishedEmailListener;

class MailQueueServiceTest extends BaseTestCase
{
    private MailQueueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MailQueueService();
    }

    public function testEnqueueAddsItemToDatabase(): void
    {
        $queueId = $this->service->enqueue(
            toEmail: 'hoca@universite.edu.tr',
            toName: 'Prof. Dr. Ahmet Hoca',
            subject: 'Ders Programınız Yayınlandı',
            body: '<p>Programınız ektedir.</p>',
            altBody: 'Programınız ektedir.',
            attachments: [
                ['name' => 'program.xlsx', 'content' => base64_encode('fake excel'), 'encoding' => 'base64', 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            ]
        );

        $this->assertGreaterThan(0, $queueId);

        $item = (new MailQueue())->find($queueId);
        $this->assertNotNull($item);
        $this->assertEquals('hoca@universite.edu.tr', $item->to_email);
        $this->assertEquals('Prof. Dr. Ahmet Hoca', $item->to_name);
        $this->assertEquals('Ders Programınız Yayınlandı', $item->subject);
        $this->assertEquals(MailQueueStatus::Pending->value, $item->status);
        $this->assertEquals(0, $item->attempts);
        $this->assertNotNull($item->attachments);
    }

    public function testProcessQueueProcessesPendingItems(): void
    {
        $queueId = $this->service->enqueue(
            toEmail: 'test2@universite.edu.tr',
            toName: 'Doç. Dr. Ayşe Hoca',
            subject: 'Test Konu',
            body: '<p>Test Mesajı</p>'
        );

        $result = $this->service->processQueue(10);
        $this->assertGreaterThanOrEqual(1, $result['processed']);
        $this->assertGreaterThanOrEqual(1, $result['sent']);

        $item = (new MailQueue())->find($queueId);
        $this->assertEquals(MailQueueStatus::Sent->value, $item->status);
        $this->assertNotNull($item->sent_at);
        $this->assertNull($item->error_message);
    }

    public function testGetQueueStats(): void
    {
        $this->service->enqueue('stat1@test.com', null, 'Sub1', 'Body1');
        $this->service->enqueue('stat2@test.com', null, 'Sub2', 'Body2');

        $stats = $this->service->getQueueStats();
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('sent', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertGreaterThanOrEqual(2, $stats['pending']);
    }

    public function testSchedulePublishedListenerQueuesEmail(): void
    {
        $lecturer = new User();
        $lecturer->fill([
            'name'      => 'Dr. Mehmet',
            'last_name' => 'Demir',
            'mail'      => 'mehmet.demir' . rand(1000, 9999) . '@univ.edu.tr',
            'password'  => password_hash('123456', PASSWORD_DEFAULT),
            'role'      => 'lecturer',
            'title'     => 'Dr. Öğr. Üyesi'
        ]);
        $lecturer->create();

        $schedule = new Schedule();
        $schedule->fill([
            'type'          => 'lesson',
            'owner_type'    => OwnerType::USER->value,
            'owner_id'      => $lecturer->id,
            'semester'      => 'Güz',
            'academic_year' => '2026-2027',
            'is_published'  => 1
        ]);
        $schedule->create();

        $listener = new SendSchedulePublishedEmailListener();
        $listener->handle(new SchedulePublishedEvent($schedule->id));

        $queuedItem = (new MailQueue())->get()->where(['to_email' => $lecturer->mail])->first();

        $this->assertNotNull($queuedItem, 'Yayınlama sonrası e-posta kuyruğa eklenmiş olmalıdır');
        $this->assertEquals(MailQueueStatus::Pending->value, $queuedItem->status);
        $this->assertStringContainsString('Ders Programınız Yayınlandı', $queuedItem->subject);
    }

    public function testRetryFailedMovesFailedItemsToPending(): void
    {
        $queueId = $this->service->enqueue('retry@test.com', 'Retry User', 'Retry Sub', 'Body');
        $item = (new MailQueue())->find($queueId);
        $item->status = MailQueueStatus::Failed->value;
        $item->attempts = 3;
        $item->error_message = 'Connection timeout';
        $item->update();

        $count = $this->service->retryFailed();
        $this->assertGreaterThanOrEqual(1, $count);

        $itemReloaded = (new MailQueue())->find($queueId);
        $this->assertEquals(MailQueueStatus::Pending->value, $itemReloaded->status);
        $this->assertEquals(0, $itemReloaded->attempts);
        $this->assertNull($itemReloaded->error_message);
    }

    public function testClearSentLogsDeletesSentItems(): void
    {
        $queueId = $this->service->enqueue('sent@test.com', 'Sent User', 'Sent Sub', 'Body');
        $item = (new MailQueue())->find($queueId);
        $item->status = MailQueueStatus::Sent->value;
        $item->sent_at = date('Y-m-d H:i:s');
        $item->update();

        $count = $this->service->clearSentLogs();
        $this->assertGreaterThanOrEqual(1, $count);

        $itemReloaded = (new MailQueue())->find($queueId);
        $this->assertNull($itemReloaded);
    }

    public function testDeleteItemRemovesItem(): void
    {
        $queueId = $this->service->enqueue('delete@test.com', 'Delete User', 'Delete Sub', 'Body');
        $this->assertNotNull((new MailQueue())->find($queueId));

        $deleted = $this->service->deleteItem($queueId);
        $this->assertTrue($deleted);
        $this->assertNull((new MailQueue())->find($queueId));
    }
}
