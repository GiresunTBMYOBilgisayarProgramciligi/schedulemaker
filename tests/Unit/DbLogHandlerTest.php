<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\DbLogHandler;
use Monolog\Level;

class DbLogHandlerTest extends TestCase
{
    public function testHandlerInstantiatesWithoutImmediateConnection(): void
    {
        $handler = new DbLogHandler(Level::Debug, true);
        $this->assertInstanceOf(DbLogHandler::class, $handler);

        // Reflection ile pdo property'sinin başlangıçta null olduğunu doğrula (lazy loading)
        $ref = new \ReflectionClass($handler);
        $prop = $ref->getProperty('pdo');
        $this->assertNull($prop->getValue($handler));
    }
}
