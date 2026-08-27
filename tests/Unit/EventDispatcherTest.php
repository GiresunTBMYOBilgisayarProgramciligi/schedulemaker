<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Core\EventDispatcher;

class EventDispatcherTest extends BaseTestCase
{
    public function testSingletonInstance(): void
    {
        $instance1 = EventDispatcher::getInstance();
        $instance2 = EventDispatcher::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testDispatchCallableListener(): void
    {
        $dispatcher = new EventDispatcher();
        $handled = false;
        $eventData = null;

        $event = new class {
            public string $name = 'UserRegistered';
        };

        $dispatcher->listen(get_class($event), function ($e) use (&$handled, &$eventData) {
            $handled = true;
            $eventData = $e->name;
        });

        $dispatcher->dispatch($event);

        $this->assertTrue($handled);
        $this->assertEquals('UserRegistered', $eventData);
    }

    public function testDispatchWithNoListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $event = new class {
            public string $name = 'UnlistenedEvent';
        };

        // Herhangi bir hata atmadan tamamlanmalı
        $dispatcher->dispatch($event);
        $this->assertTrue(true);
    }
}
