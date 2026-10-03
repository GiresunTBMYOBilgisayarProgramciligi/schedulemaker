<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Services\LogReaderService;

class LogReaderServiceTest extends BaseTestCase
{
    private string $tempLogDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempLogDir = sys_get_temp_dir() . '/schedulemaker_test_logs_' . uniqid();
        if (!is_dir($this->tempLogDir)) {
            mkdir($this->tempLogDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempLogDir)) {
            $files = glob($this->tempLogDir . '/*') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempLogDir);
        }
        parent::tearDown();
    }

    public function testGetAvailableChannelsReturnsDefaultsAndDiscovered(): void
    {
        file_put_contents($this->tempLogDir . '/customchannel-2026-10-03.log', '');
        file_put_contents($this->tempLogDir . '/audit.log', '');

        $service = new LogReaderService($this->tempLogDir);
        $channels = $service->getAvailableChannels();

        $this->assertContains('app', $channels);
        $this->assertContains('security', $channels);
        $this->assertContains('customchannel', $channels);
        $this->assertContains('audit', $channels);
    }

    public function testGetLogsParsesJsonAndOrdersReversed(): void
    {
        $log1 = json_encode([
            'message' => 'First message',
            'context' => ['username' => 'user1', 'ip' => '127.0.0.1'],
            'level_name' => 'INFO',
            'channel' => 'app',
            'datetime' => '2026-10-03 10:00:00'
        ]);
        $log2 = json_encode([
            'message' => 'Second message (error)',
            'context' => ['username' => 'admin', 'ip' => '192.168.1.1'],
            'level_name' => 'ERROR',
            'channel' => 'security',
            'datetime' => '2026-10-03 10:05:00'
        ]);

        file_put_contents($this->tempLogDir . '/app-2026-10-03.log', $log1 . "\n" . $log2 . "\n");

        $service = new LogReaderService($this->tempLogDir);
        $logs = $service->getLogs([], 10);

        $this->assertCount(2, $logs);
        // En son satır (log2) en başta olmalı
        $this->assertEquals('Second message (error)', $logs[0]->message);
        $this->assertEquals('ERROR', $logs[0]->level);
        $this->assertEquals('security', $logs[0]->channel);
        $this->assertEquals('admin', $logs[0]->username);

        $this->assertEquals('First message', $logs[1]->message);
        $this->assertEquals('INFO', $logs[1]->level);
    }

    public function testGetLogsFiltersByLevelAndSearch(): void
    {
        $log1 = json_encode([
            'message' => 'User login success',
            'context' => ['username' => 'john'],
            'level_name' => 'INFO',
            'channel' => 'auth',
            'datetime' => '2026-10-03 10:00:00'
        ]);
        $log2 = json_encode([
            'message' => 'Failed login attempt',
            'context' => ['username' => 'hacker'],
            'level_name' => 'WARNING',
            'channel' => 'auth',
            'datetime' => '2026-10-03 10:01:00'
        ]);
        $log3 = json_encode([
            'message' => 'Critical database error',
            'context' => ['username' => 'system'],
            'level_name' => 'ERROR',
            'channel' => 'database',
            'datetime' => '2026-10-03 10:02:00'
        ]);

        file_put_contents($this->tempLogDir . '/auth-2026-10-03.log', $log1 . "\n" . $log2 . "\n");
        file_put_contents($this->tempLogDir . '/database-2026-10-03.log', $log3 . "\n");

        $service = new LogReaderService($this->tempLogDir);

        // Seviye filtresi
        $errorLogs = $service->getLogs(['level' => 'ERROR']);
        $this->assertCount(1, $errorLogs);
        $this->assertEquals('Critical database error', $errorLogs[0]->message);

        // Arama filtresi
        $searchLogs = $service->getLogs(['search' => 'hacker']);
        $this->assertCount(1, $searchLogs);
        $this->assertEquals('Failed login attempt', $searchLogs[0]->message);

        // Kanal filtresi
        $authLogs = $service->getLogs(['channel' => 'auth']);
        $this->assertCount(2, $authLogs);
    }

    public function testClearLogsDeletesChannelOrAllFiles(): void
    {
        file_put_contents($this->tempLogDir . '/app-2026-10-03.log', "test\n");
        file_put_contents($this->tempLogDir . '/security-2026-10-03.log', "test\n");

        $service = new LogReaderService($this->tempLogDir);

        // Sadece security kanalını temizle
        $cleared = $service->clearLogs('security');
        $this->assertEquals(1, $cleared);
        $this->assertFileDoesNotExist($this->tempLogDir . '/security-2026-10-03.log');
        $this->assertFileExists($this->tempLogDir . '/app-2026-10-03.log');

        // Tümünü temizle
        $clearedAll = $service->clearLogs(null);
        $this->assertEquals(1, $clearedAll);
        $this->assertFileDoesNotExist($this->tempLogDir . '/app-2026-10-03.log');
    }
}
