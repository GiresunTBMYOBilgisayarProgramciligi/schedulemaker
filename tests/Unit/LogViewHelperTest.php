<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Helpers\LogViewHelper;
use App\Models\Log;

class LogViewHelperTest extends TestCase
{
    public function testRenderLevelBadge(): void
    {
        $log = new Log();
        $log->level = 'ERROR';
        $badge = LogViewHelper::renderLevelBadge($log);
        $this->assertStringContainsString('badge bg-danger', $badge);
        $this->assertStringContainsString('ERROR', $badge);

        $badgeInfo = LogViewHelper::renderLevelBadge('INFO');
        $this->assertStringContainsString('badge bg-info', $badgeInfo);
    }

    public function testRenderSource(): void
    {
        $log = new Log();
        $log->file = '/var/www/App/Services/ScheduleService.php';
        $log->line = 42;
        $log->class = 'App\Services\ScheduleService';
        $log->method = 'saveScheduleItems';

        $source = LogViewHelper::renderSource($log);
        $this->assertStringContainsString('ScheduleService.php:42', $source);
        $this->assertStringContainsString('App\Services\ScheduleService', $source);
        $this->assertStringContainsString('saveScheduleItems', $source);
    }

    public function testRenderContextModal(): void
    {
        $log = new Log();
        $log->id = 123;
        $log->level = 'INFO';
        $log->context = json_encode(['username' => 'testuser', 'action' => 'save']);

        $modalHtml = LogViewHelper::renderContextModal($log);
        $this->assertStringContainsString('data-bs-target="#contextModal-123"', $modalHtml);
        $this->assertStringContainsString('id="contextModal-123"', $modalHtml);
        $this->assertStringContainsString('testuser', $modalHtml);
    }
}
