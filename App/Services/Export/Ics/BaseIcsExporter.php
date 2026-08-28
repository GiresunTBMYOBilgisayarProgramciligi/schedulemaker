<?php

namespace App\Services\Export\Ics;

use App\Core\Log;
use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\Services\Export\ScheduleExporterInterface;
use App\Services\Export\ScheduleExportFilterBuilder;
use JetBrains\PhpStorm\NoReturn;
use Monolog\Logger;
use function App\Helpers\getSettingValue;

/**
 * ICS dışa aktarma sınıfları için ortak altyapı:
 * - Tarih çözme ve takvim ayarları
 * - ICS formatı string kaçırma (escape)
 * - Dosyayı tarayıcıya indirme olarak gönderme
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
     * İlgili program türü için başlangıç ve bitiş tarihlerini ayarlardan çeker.
     *
     * @param \DateTimeZone $timezone
     * @param string $type
     * @return array{startDate: ?\DateTime, endDate: ?\DateTime}
     */
    protected function getScheduleDates(\DateTimeZone $timezone, string $type = 'lesson'): array
    {
        $startDateStr = getSettingValue('startDate', $type, '');
        $endDateStr   = getSettingValue('endDate', $type, '');

        $startDate = !empty($startDateStr) ? new \DateTime($startDateStr, $timezone) : null;
        $endDate   = !empty($endDateStr)   ? new \DateTime($endDateStr, $timezone)   : null;

        return [
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ];
    }

    /**
     * ICS formatı için metin kaçırma (escape) işlemi.
     */
    protected function escapeIcsText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace("\n", '\n', $text);
        $text = str_replace("\r", '', $text);
        return $text;
    }

    /**
     * Dosya adını üretir.
     */
    public function getFileName(ScheduleExportFilterDTO|array $filters): string
    {
        $filterDTO       = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $scheduleFilters = $this->filterBuilder->build($filterDTO);
        $lastKey         = !empty($scheduleFilters) && is_array($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastKey !== null && isset($scheduleFilters[$lastKey]['file_title'])) ? $scheduleFilters[$lastKey]['file_title'] : 'Program';
        $academicYear    = $filterDTO->academic_year ?? '';
        $semester        = $filterDTO->semester ?? '';
        $baseName        = $academicYear . "-" . $semester . "-" . $fileTitle;

        return $this->slugify($baseName) . ".ics";
    }

    /**
     * Dosyayı tarayıcıya indirme olarak gönderir.
     */
    #[NoReturn]
    public function export(ScheduleExportFilterDTO|array $filters, ScheduleExportOptionsDTO|array $showOptions = []): void
    {
        $filterDTO  = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $optionsDTO = $showOptions instanceof ScheduleExportOptionsDTO ? $showOptions : ScheduleExportOptionsDTO::fromArray($showOptions);

        $raw      = $this->buildIcs($filterDTO, $optionsDTO);
        $content  = is_array($raw) ? implode("\r\n", $raw) . "\r\n" : (string)$raw;
        $fileName = $this->getFileName($filterDTO);

        $this->download($fileName, $content);
    }

    /**
     * Dışa aktarılan ICS dosyasının ham metin içeriğini string olarak döndürür.
     */
    public function getRawContent(ScheduleExportFilterDTO|array $filters, ScheduleExportOptionsDTO|array $showOptions = []): string
    {
        $filterDTO  = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $optionsDTO = $showOptions instanceof ScheduleExportOptionsDTO ? $showOptions : ScheduleExportOptionsDTO::fromArray($showOptions);

        $raw = $this->buildIcs($filterDTO, $optionsDTO);
        return is_array($raw) ? implode("\r\n", $raw) . "\r\n" : (string)$raw;
    }

    /**
     * ICS içeriğini derler.
     *
     * @param ScheduleExportFilterDTO $filters
     * @param ScheduleExportOptionsDTO $showOptions
     * @return array|string
     */
    abstract protected function buildIcs(ScheduleExportFilterDTO $filters, ScheduleExportOptionsDTO $showOptions): array|string;

    /**
     * Dosyayı HTTP yanıtı olarak indirir.
     */
    #[NoReturn]
    protected function download(string $fileName, string $content): void
    {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        echo $content;
        exit;
    }

    /**
     * Basit slug üretici (dosya adı için)
     */
    protected function slugify(string $text): string
    {
        $turkish = ['ı', 'ğ', 'ü', 'ş', 'i', 'ö', 'ç', 'I', 'Ğ', 'Ü', 'Ş', 'İ', 'Ö', 'Ç'];
        $english = ['i', 'g', 'u', 's', 'i', 'o', 'c', 'i', 'g', 'u', 's', 'i', 'o', 'c'];
        $text = str_replace($turkish, $english, mb_strtolower($text, 'UTF-8'));
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text);
    }
}
