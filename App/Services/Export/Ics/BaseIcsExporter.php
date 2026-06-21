<?php

namespace App\Services\Export\Ics;

use App\Core\Log;
use App\Services\Export\ScheduleExporterInterface;
use App\Services\Export\ScheduleFilterBuilder;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Monolog\Logger;

/**
 * ICS takvim export sınıfları için ortak altyapı.
 */
abstract class BaseIcsExporter implements ScheduleExporterInterface
{
    protected ScheduleFilterBuilder $filterBuilder;

    public function __construct()
    {
        $this->filterBuilder = new ScheduleFilterBuilder();
    }

    protected function logger(): Logger
    {
        return Log::logger();
    }

    protected function logContext(array $extra = []): array
    {
        return Log::context($this, $extra);
    }

    /**
     * ICS metinlerini RFC 5545 uyumlu şekilde kaçırır.
     */
    protected function escapeIcsText(string $text): string
    {
        return str_replace(["\\", ",", ";", "\n", "\r"], ["\\\\", "\\,", "\\;", "\\n", ""], $text);
    }

    /**
     * Basit slug üretici (dosya adı için)
     */
    protected function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text);
    }

    /**
     * ICS içeriğini tarayıcıya indirilecek dosya olarak gönderir.
     */
    #[NoReturn]
    protected function sendIcsResponse(array $lines, string $fileName): void
    {
        $content = implode("\r\n", $lines) . "\r\n";
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        echo $content;
        exit;
    }

    /**
     * Akademik dönem başlangıç/bitiş tarihlerini ayarlardan alır.
     * @return array{semesterStart: \DateTime|null, semesterEnd: \DateTime|null}
     */
    protected function getSemesterDates(\DateTimeZone $timezone): array
    {
        $startDateStr = \App\Helpers\getSettingValue('lesson_start_date', 'lesson');
        $endDateStr   = \App\Helpers\getSettingValue('lesson_end_date', 'lesson');
        $semesterStart = null;
        $semesterEnd   = null;

        if (!empty($startDateStr) && !empty($endDateStr)) {
            try {
                $semesterStart = new \DateTime($startDateStr, $timezone);
                $semesterEnd   = new \DateTime($endDateStr, $timezone);
            } catch (\Throwable) {
                // Tarihler ayarlanmamışsa null kalır
            }
        }

        return ['semesterStart' => $semesterStart, 'semesterEnd' => $semesterEnd];
    }
}
