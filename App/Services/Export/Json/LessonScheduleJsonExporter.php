<?php

declare(strict_types=1);

namespace App\Services\Export\Json;

use App\Core\Gate;
use App\Core\Log;
use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\Enums\PermissionType;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Services\Export\ScheduleExportFilterBuilder;
use App\Services\Export\ScheduleExporterInterface;
use JetBrains\PhpStorm\NoReturn;
use Monolog\Logger;
use function App\Helpers\getClassFromSemesterNo;

/**
 * Ders programını JSON formatında dışa aktarır.
 *
 * Her kayıt:
 *   ders_kodu, ders_adi, program, bina, derslik, hoca, gun, baslangic_saati, bitis_saati
 */
class LessonScheduleJsonExporter implements ScheduleExporterInterface
{
    /** @var string[] Gün isimleri Türkçe karşılıkları */
    private const DAYS_TR = [
        'Pazartesi',
        'Salı',
        'Çarşamba',
        'Perşembe',
        'Cuma',
        'Cumartesi',
        'Pazar',
    ];

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
     * Dışa aktarma verilerini dizi olarak üretir.
     *
     * @throws \Exception
     */
    protected function buildData(ScheduleExportFilterDTO $filters, ScheduleExportOptionsDTO $showOptions): array
    {
        $scheduleFilters = $this->filterBuilder->build($filters);
        $lastFilterKey   = !empty($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastFilterKey !== null && isset($scheduleFilters[$lastFilterKey]['file_title']))
            ? $scheduleFilters[$lastFilterKey]['file_title']
            : 'Ders Programı';

        $username = $this->logContext()['username'] ?? 'Misafir';
        $this->logger()->info(
            "{$username} {$fileTitle} JSON çıktısı aldı.",
            $this->logContext()
        );

        $rows = [];

        foreach ($scheduleFilters as $scheduleFilter) {
            $schedule = (new Schedule())->get()
                ->where($scheduleFilter['filter'])
                ->with('items')
                ->first();

            if (!$schedule || empty($schedule->items)) {
                continue;
            }

            if (!Gate::check(PermissionType::VIEW->value, $schedule)) {
                continue;
            }

            foreach ($schedule->items as $item) {
                /** @var ScheduleItem $item */
                $dayName   = self::DAYS_TR[$item->day_index] ?? '';
                $startTime = $item->getShortStartTime();
                $endTime   = $item->getShortEndTime();

                $slotDatas = $item->getSlotDatas();

                foreach ($slotDatas as $data) {
                    $lesson = $data->lesson;

                    $code       = $lesson?->code ?? '';
                    $lessonName = $lesson?->getFullName(addGroup: true) ?? '';

                    // Program/Bölüm
                    $programName = '';
                    if ($lesson?->program) {
                        $programName = $lesson->program->name;
                        if ($lesson->semester_no) {
                            $programName .= ' - ' . getClassFromSemesterNo($lesson->semester_no);
                        }
                    }
                    if (!empty($lesson?->childLessons)) {
                        $childPrograms = [];
                        foreach ($lesson->childLessons as $child) {
                            if ($child?->program) {
                                $childProgramStr = $child->program->name;
                                if ($lesson->semester_no) {
                                    $childProgramStr .= ' - ' . getClassFromSemesterNo($lesson->semester_no);
                                }
                                $childPrograms[] = $childProgramStr;
                            }
                        }
                        if (!empty($childPrograms)) {
                            $allPrograms = array_unique(array_merge([$programName], $childPrograms));
                            $programName = implode(', ', array_filter($allPrograms));
                        }
                    }

                    $classroomName = '';
                    $buildingName  = '';
                    if (!empty($data->classroom)) {
                        $classroomName = $data->classroom->name;
                        $buildingName  = $data->classroom->building?->name ?? '';
                    }

                    $lecturerName = '';
                    if ($scheduleFilter['type'] !== 'user' && !empty($data->lecturer)) {
                        $lecturerName = $data->lecturer->getFullName();
                    }

                    $rows[] = [
                        'ders_kodu'       => $code,
                        'ders_adi'        => $lessonName,
                        'program'         => $programName,
                        'bina'            => $buildingName,
                        'derslik'         => $classroomName,
                        'hoca'            => $lecturerName,
                        'gun'             => $dayName,
                        'baslangic_saati' => $startTime,
                        'bitis_saati'     => $endTime,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Dosya adını üretir.
     */
    public function getFileName(ScheduleExportFilterDTO|array $filters): string
    {
        $filterDTO       = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $scheduleFilters = $this->filterBuilder->build($filterDTO);
        $lastKey         = !empty($scheduleFilters) ? array_key_last($scheduleFilters) : null;
        $fileTitle       = ($lastKey !== null && isset($scheduleFilters[$lastKey]['file_title']))
            ? $scheduleFilters[$lastKey]['file_title']
            : 'Program';
        $academicYear    = $filterDTO->academic_year ?? '';
        $semester        = $filterDTO->semester ?? '';
        $baseName        = $academicYear . '-' . $semester . '-' . $fileTitle . '-Liste';

        return $this->slugify($baseName) . '.json';
    }

    /**
     * JSON içeriğini string olarak döndürür.
     *
     * @throws \Exception
     */
    public function getRawContent(ScheduleExportFilterDTO|array $filters, ScheduleExportOptionsDTO|array $showOptions = []): string
    {
        $filterDTO  = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $optionsDTO = $showOptions instanceof ScheduleExportOptionsDTO ? $showOptions : ScheduleExportOptionsDTO::fromArray($showOptions);

        $data = $this->buildData($filterDTO, $optionsDTO);

        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * JSON dosyasını tarayıcıya indirme olarak gönderir.
     *
     * @throws \Exception
     */
    #[NoReturn]
    public function export(ScheduleExportFilterDTO|array $filters, ScheduleExportOptionsDTO|array $showOptions = []): void
    {
        $filterDTO  = $filters instanceof ScheduleExportFilterDTO ? $filters : ScheduleExportFilterDTO::fromArray($filters);
        $optionsDTO = $showOptions instanceof ScheduleExportOptionsDTO ? $showOptions : ScheduleExportOptionsDTO::fromArray($showOptions);

        $json     = $this->getRawContent($filterDTO, $optionsDTO);
        $fileName = $this->getFileName($filterDTO);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        echo $json;
        exit;
    }

    /**
     * Basit slug üretici (dosya adı için).
     */
    protected function slugify(string $text): string
    {
        $turkish = ['ı', 'ğ', 'ü', 'ş', 'i', 'ö', 'ç', 'I', 'Ğ', 'Ü', 'Ş', 'İ', 'Ö', 'Ç'];
        $english = ['i', 'g', 'u', 's', 'i', 'o', 'c', 'i', 'g', 'u', 's', 'i', 'o', 'c'];
        $text    = str_replace($turkish, $english, mb_strtolower($text, 'UTF-8'));
        $text    = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text    = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text    = preg_replace('~[^-\w]+~', '', $text);
        $text    = trim($text, '-');
        $text    = preg_replace('~-+~', '-', $text);
        return strtolower($text);
    }
}
