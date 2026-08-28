<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Enums\MailQueueStatus;
use App\Models\MailQueue;

class MailQueueTest extends BaseTestCase
{
    public function testMailQueueStatusEnum(): void
    {
        $this->assertEquals('pending', MailQueueStatus::Pending->value);
        $this->assertEquals('Bekliyor', MailQueueStatus::Pending->getLabel());
        $this->assertEquals('İşleniyor', MailQueueStatus::Processing->getLabel());
        $this->assertEquals('Gönderildi', MailQueueStatus::Sent->getLabel());
        $this->assertEquals('Başarısız', MailQueueStatus::Failed->getLabel());
    }

    public function testMailQueueModelAttributes(): void
    {
        $queue = new MailQueue();
        $queue->fill([
            'to_email' => 'test@example.com',
            'to_name'  => 'Test User',
            'subject'  => 'Test Subject',
            'body'     => '<p>Hello World</p>',
            'status'   => MailQueueStatus::Pending->value
        ]);

        $this->assertEquals('test@example.com', $queue->to_email);
        $this->assertEquals('Test User', $queue->to_name);
        $this->assertEquals('Test Subject', $queue->subject);
        $this->assertEquals(MailQueueStatus::Pending, $queue->getStatusEnum());
        $this->assertEquals('e-posta kuyruğu', $queue->getLabel());
    }
}
