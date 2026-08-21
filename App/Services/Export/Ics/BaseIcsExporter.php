<?php

namespace App\Services\Export\Ics;

use App\Core\Log;
use App\Enums\ExamType;
use App\Services\Export\ScheduleExporterInterface;
use App\Services\Export\ScheduleExportFilterBuilder;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Monolog\Logger;

use function App\Helpers\getSettingValue;

/**
 * ICS takvim export sınıfları için ortak altyapı.
 */
abstract class BaseIcsExporter implements ScheduleExporterInterface
{
    protected ScheduleExportFilterBuilder $filterBuilder;

    public function __construct()
    {
        $this->filterBuilder = new ScheduleExportFilterBuilder();
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
     * ICS satırlarını ve dosya adını derler.
     * @return array{lines: array, fileName: string}
     */
    abstract protected function buildIcs(array $filters, array $showOptions): array;

    /**
     * ICS içeriğini tarayıcıya indirme olarak gönderir.
     */
    #[NoReturn]
    public function export(array $filters, array $showOptions): void
    {
        $result = $this->buildIcs($filters, $showOptions);
        $this->sendIcsResponse($result['lines'], $result['fileName']);
    }

    /**
     * ICS dosya içeriğini metin (string) olarak döner.
     */
    public function getRawContent(array $filters, array $showOptions): string
    {
        $result = $this->buildIcs($filters, $showOptions);
        return implode("\r\n", $result['lines']) . "\r\n";
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
     * İlgili program türüne (ders veya sınav) göre başlangıç ve bitiş tarihlerini ayarlardan alır.
     * @return array{startDate: \DateTime|null, endDate: \DateTime|null}
     */
    protected function getScheduleDates(\DateTimeZone $timezone, string $type = 'lesson'): array
    {
        if (ExamType::isExamType($type)) {
            $settingKey = ExamType::tryFrom($type)->startDateSettingKey();
            $startDateStr = getSettingValue($settingKey, 'exam');
            $endDateStr   = null;
        } else {
            $startDateStr = getSettingValue('lesson_start_date', 'lesson');
            $endDateStr   = getSettingValue('lesson_end_date', 'lesson');
        }

        $startDate = null;
        $endDate   = null;

        if (!empty($startDateStr)) {
            try {
                $startDate = new \DateTime($startDateStr, $timezone);
            } catch (\Throwable) {
                // Tarihler ayarlanmamışsa null kalır
            }
        }
        
        if (!empty($endDateStr)) {
            try {
                $endDate = new \DateTime($endDateStr, $timezone);
            } catch (\Throwable) {
                // Tarihler ayarlanmamışsa null kalır
            }
        }

        return ['startDate' => $startDate, 'endDate' => $endDate];
    }
}
