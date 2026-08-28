<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Repositories\MailQueueRepository;
use App\Models\MailQueue;
use App\Enums\MailQueueStatus;

class MailQueueRepositoryTest extends BaseTestCase
{
    private MailQueueRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MailQueueRepository();
    }

    public function testGetQueueStatsReturnsAccurateCounts(): void
    {
        $uniqueMail = 'stat_test_' . rand(1000, 9999) . '@test.com';
        $queue = new MailQueue();
        $queue->fill([
            'to_email' => $uniqueMail,
            'subject'  => 'Stats Test',
            'body'     => 'Body',
            'status'   => MailQueueStatus::Pending->value
        ]);
        $queue->create();

        $stats = $this->repository->getQueueStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('processing', $stats);
        $this->assertArrayHasKey('sent', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['pending']);
    }

    public function testAtomicLockItemPreventsConcurrentRaceCondition(): void
    {
        $queue = new MailQueue();
        $queue->fill([
            'to_email' => 'lock_test@test.com',
            'subject'  => 'Lock Test',
            'body'     => 'Body',
            'status'   => MailQueueStatus::Pending->value,
            'attempts' => 0
        ]);
        $queue->create();

        // 1. İlk kilit başarılı olmalı
        $lockedFirst = $this->repository->atomicLockItem($queue->id);
        $this->assertTrue($lockedFirst);

        $fresh = (new MailQueue())->find($queue->id);
        $this->assertEquals(MailQueueStatus::Processing->value, $fresh->status);
        $this->assertEquals(1, $fresh->attempts);

        // 2. İkinci eşzamanlı kilit denemesi başarısız olmalı (çünkü artık processing durumunda)
        $lockedSecond = $this->repository->atomicLockItem($queue->id);
        $this->assertFalse($lockedSecond);
    }

    public function testGetPendingItemsReturnsOrderedByAscendingId(): void
    {
        $queue1 = new MailQueue();
        $queue1->fill(['to_email' => 'fifo1@test.com', 'subject' => 'FIFO 1', 'body' => 'Body', 'status' => MailQueueStatus::Pending->value]);
        $queue1->create();

        $queue2 = new MailQueue();
        $queue2->fill(['to_email' => 'fifo2@test.com', 'subject' => 'FIFO 2', 'body' => 'Body', 'status' => MailQueueStatus::Pending->value]);
        $queue2->create();

        $items = $this->repository->getPendingItems(50, 3);
        $this->assertNotEmpty($items);

        $foundIndex1 = null;
        $foundIndex2 = null;
        foreach ($items as $idx => $item) {
            if ($item->id === $queue1->id) $foundIndex1 = $idx;
            if ($item->id === $queue2->id) $foundIndex2 = $idx;
        }

        $this->assertNotNull($foundIndex1);
        $this->assertNotNull($foundIndex2);
        $this->assertLessThan($foundIndex2, $foundIndex1, "FIFO sıralamasında ilk eklenen önce gelmelidir.");
    }
}
