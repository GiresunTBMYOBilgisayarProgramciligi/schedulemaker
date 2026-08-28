<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Core\Log;
use Monolog\Handler\NullHandler;

class LogTest extends BaseTestCase
{
    public function testLoggerUsesNullHandlerInTestingEnvironment(): void
    {
        Log::reset();
        $logger = Log::logger();

        $handlers = $logger->getHandlers();
        $this->assertNotEmpty($handlers);
        $this->assertInstanceOf(NullHandler::class, $handlers[0]);
    }

    public function testLoggingDoesNotInsertIntoDatabaseDuringTests(): void
    {
        Log::reset();
        $db = $this->getDb();

        $initialCount = (int)$db->query("SELECT COUNT(*) FROM logs")->fetchColumn();

        Log::logger()->info("Bu bir test log kaydıdır ve DB'ye yazılmamalıdır", Log::context($this));
        Log::logger()->error("Bu bir test hata log kaydıdır ve DB'ye yazılmamalıdır", Log::context($this));

        $finalCount = (int)$db->query("SELECT COUNT(*) FROM logs")->fetchColumn();
        $this->assertEquals($initialCount, $finalCount);
    }
}
