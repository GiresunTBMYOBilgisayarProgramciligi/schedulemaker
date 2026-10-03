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

    public function testLogsTableDoesNotExistAndDbLoggingIsDisabled(): void
    {
        Log::reset();
        $db = $this->getDb();

        // logs tablosunun veritabanından tamamen silindiğini doğrula
        $tables = $db->query("SHOW TABLES LIKE 'logs'")->fetchAll();
        $this->assertEmpty($tables, "Veritabanında logs tablosu bulunmamalıdır.");

        // Logger'ın DB handler kullanmadığını doğrula
        $logger = Log::logger();
        foreach ($logger->getHandlers() as $handler) {
            $this->assertNotInstanceOf(\Monolog\Handler\AbstractProcessingHandler::class, $handler, 
                get_class($handler) === \App\Core\AppRotatingFileHandler::class ? '' : 'Logger veritabanı işleyicisi içermemelidir.'
            );
        }
    }

    public function testChannelReturnsLoggerForGivenChannel(): void
    {
        Log::reset();
        $authLogger = Log::channel('auth');
        $this->assertEquals('auth', $authLogger->getName());

        $securityLogger = Log::channel('security');
        $this->assertEquals('security', $securityLogger->getName());
    }

    public function testChannelCachesInstances(): void
    {
        Log::reset();
        $logger1 = Log::channel('schedule');
        $logger2 = Log::channel('schedule');
        $this->assertSame($logger1, $logger2);
    }

    public function testLoggerInitializationDoesNotCauseCircularRecursion(): void
    {
        Log::reset();
        // Logger ilk kez çağrıldığında BaseService veya Settings bağımlılıklarında
        // özyinelemeli döngüye girmeden güvenli ve hızlı şekilde nesne üretmelidir
        $startTime = microtime(true);
        $logger = Log::logger();
        $elapsed = microtime(true) - $startTime;

        $this->assertInstanceOf(\Monolog\Logger::class, $logger);
        $this->assertLessThan(1.0, $elapsed, 'Logger initialization should complete in less than 1 second without recursion');
    }

    public function testCleanExpiredLogsRemovesOnlyExpiredFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/schedulemaker_log_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        // 20 gün önceki dosya (eski, silinmeli)
        $oldFile = $tempDir . '/app-old.log';
        file_put_contents($oldFile, 'old log');
        touch($oldFile, time() - (20 * 86400));

        // 5 gün önceki dosya (yeni, saklanmalı)
        $recentFile = $tempDir . '/app-recent.log';
        file_put_contents($recentFile, 'recent log');
        touch($recentFile, time() - (5 * 86400));

        $deletedCount = Log::cleanExpiredLogs($tempDir, 14);

        $this->assertEquals(1, $deletedCount);
        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);

        // Temizlik
        @unlink($recentFile);
        @rmdir($tempDir);
    }

    public function testAppRotatingFileHandlerSupportsWeeklyFormat(): void
    {
        $tempDir = sys_get_temp_dir() . '/schedulemaker_handler_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        $handler = new \App\Core\AppRotatingFileHandler(
            $tempDir . '/test.log',
            5,
            \Monolog\Level::Debug,
            true,
            null
        );
        $handler->setFilenameFormat('{filename}-{date}', \App\Core\AppRotatingFileHandler::FILE_PER_WEEK);

        $record = new \Monolog\LogRecord(
            new \DateTimeImmutable(),
            'test',
            \Monolog\Level::Info,
            'Weekly log record'
        );
        $handler->handle($record);
        $handler->close();

        $files = glob($tempDir . '/test-*.log');
        $this->assertNotEmpty($files);
        $this->assertMatchesRegularExpression('/test-\d{4}-W\d{2}\.log$/', $files[0]);

        // Temizlik
        foreach ($files as $f) {
            @unlink($f);
        }
        @rmdir($tempDir);
    }

    public function testIsDebugHelperEvaluatesBooleanValuesCorrectly(): void
    {
        $backup = $_ENV['DEBUG'] ?? null;

        $_ENV['DEBUG'] = 'true';
        $this->assertTrue(isDebug());

        $_ENV['DEBUG'] = '1';
        $this->assertTrue(isDebug());

        $_ENV['DEBUG'] = 'false';
        $this->assertFalse(isDebug());

        $_ENV['DEBUG'] = '0';
        $this->assertFalse(isDebug());

        unset($_ENV['DEBUG']);
        $this->assertFalse(isDebug());

        // Eski değeri geri yükle
        if ($backup !== null) {
            $_ENV['DEBUG'] = $backup;
        } else {
            unset($_ENV['DEBUG']);
        }
    }
}

