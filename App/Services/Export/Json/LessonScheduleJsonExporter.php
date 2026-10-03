<?php

declare(strict_types=1);

namespace App\Services\Export\Json;

use App\Core\Gate;
use App\Core\Log;
use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;
use App\Enums\ClassroomType;
use App\Enums\LessonType;
use App\Enums\OwnerType;
use App\Enums\PermissionType;
use App\Middlewares\AuthMiddleware;
use App\Models\Building;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\Unit;
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
    /** @var array<string, array{0: string, 1: string}> Staj derslik/bina önbelleği */
    private array $resolvedInternshipLocationCache = [];
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

            if (!$schedule && $scheduleFilter['type'] === OwnerType::PROGRAM->value) {
                // Schedule kaydı henüz oluşmamışsa geçici nesne oluşturulup yetki ve staj kontrolleri yürütülür
                $schedule = new Schedule();
                $schedule->owner_type    = OwnerType::PROGRAM->value;
                $schedule->owner_id      = (int)($scheduleFilter['filter']['owner_id'] ?? 0);
                $schedule->semester_no   = isset($scheduleFilter['filter']['semester_no']) ? (int)$scheduleFilter['filter']['semester_no'] : null;
                $schedule->semester      = $scheduleFilter['filter']['semester'] ?? null;
                $schedule->academic_year = $scheduleFilter['filter']['academic_year'] ?? null;
                $schedule->type          = $scheduleFilter['filter']['type'] ?? 'lesson';
                $schedule->is_published  = true;
            }

            if (!$schedule) {
                continue;
            }

            if (!Gate::check(PermissionType::VIEW->value, $schedule)) {
                continue;
            }

            if (!empty($schedule->items)) {
                foreach ($schedule->items as $item) {
                    /** @var ScheduleItem $item */
                    foreach ($item->getSlotDatas() as $data) {
                        $rows[] = $this->formatScheduleRow($item, $data, $scheduleFilter['type'] === 'user');
                    }
                }
            }

            // Staj dersleri: Kullanıcı staj derslerini seçmişse ve yetkiliyse programa bağlı staj derslerini ekle
            $currentUser = AuthMiddleware::user();
            $canViewInternship = ($currentUser !== null) && (
                Gate::check(PermissionType::UPDATE->value, $schedule) || 
                Gate::check(PermissionType::VIEW->value, $schedule)
            );
            $isLessonSchedule = ($scheduleFilter['filter']['type'] ?? ($schedule->type ?? 'lesson')) === 'lesson';

            if ($canViewInternship && $showOptions->showInternship && $scheduleFilter['type'] === OwnerType::PROGRAM->value && $isLessonSchedule) {
                $this->appendInternshipRows($rows, $scheduleFilter, $schedule);
            }
        }

        return $rows;
    }

    /**
     * Program öğesi ve slot verisinden dışa aktarım satır verisini üretir.
     *
     * @param ScheduleItem $item
     * @param object $data
     * @param bool $isUserFilter
     * @return array<string, string>
     */
    protected function formatScheduleRow(ScheduleItem $item, object $data, bool $isUserFilter = false): array
    {
        $lesson = $data->lesson;

        $code        = $lesson?->code ?? '';
        $lessonName  = $lesson?->getFullName(addGroup: true) ?? '';
        $programName = $this->resolveProgramName($lesson);

        $classroomName = '';
        $buildingName  = '';
        if (!empty($data->classroom)) {
            $classroomName = $data->classroom->name;
            $buildingName  = $data->classroom->building?->name ?? '';
        } elseif ($lesson && (int)$lesson->type === LessonType::INTERNSHIP->value) {
            [$buildingName, $classroomName] = $this->resolveBuildingAndClassroomForInternship($lesson, $data);
        }

        $lecturerName = '';
        if (!$isUserFilter) {
            if (!empty($data->lecturer)) {
                $lecturerName = $data->lecturer->getFullName();
            } elseif (!empty($lesson?->lecturer)) {
                $lecturerName = $lesson->lecturer->getFullName();
            }
        }

        return [
            'ders_kodu'       => $code,
            'ders_adi'        => $lessonName,
            'program'         => $programName,
            'bina'            => $buildingName,
            'derslik'         => $classroomName,
            'hoca'            => $lecturerName,
            'gun'             => self::DAYS_TR[$item->day_index] ?? '',
            'baslangic_saati' => $item->getShortStartTime(),
            'bitis_saati'     => $item->getShortEndTime(),
        ];
    }

    /**
     * Ders ve alt dersler için program adı metnini oluşturur.
     */
    protected function resolveProgramName(?Lesson $lesson): string
    {
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

        return $programName;
    }

    /**
     * Staj derslerini JSON satırları arasına ekler.
     *
     * @param array $rows
     * @param array $scheduleFilter
     * @param Schedule $schedule
     * @throws \Exception
     */
    protected function appendInternshipRows(array &$rows, array $scheduleFilter, Schedule $schedule): void
    {
        $programId    = (int)($scheduleFilter['filter']['owner_id'] ?? $schedule->owner_id);
        $semesterNo   = isset($scheduleFilter['filter']['semester_no']) ? (int)$scheduleFilter['filter']['semester_no'] : (int)$schedule->semester_no;
        $academicYear = $scheduleFilter['filter']['academic_year'] ?? $schedule->academic_year;
        $semester     = $scheduleFilter['filter']['semester'] ?? $schedule->semester;
        $type         = $scheduleFilter['filter']['type'] ?? $schedule->type ?? 'lesson';

        $internshipLessons = (new Lesson())->get()->where([
            'program_id'  => $programId,
            'semester_no' => $semesterNo,
            'type'        => LessonType::INTERNSHIP->value,
        ])->with(['lecturer', 'program', 'building', 'childLessons'])->all();

        if (empty($internshipLessons)) {
            return;
        }

        $lessonIds = array_column($internshipLessons, 'id');
        $lessonSchedules = (new Schedule())->get()->where([
            'owner_type'    => OwnerType::LESSON->value,
            'owner_id'      => ['in' => $lessonIds],
            'type'          => $type,
            'semester'      => $semester,
            'academic_year' => $academicYear,
        ])->with(['items'])->all();

        foreach ($lessonSchedules as $ls) {
            if (empty($ls->items)) {
                continue;
            }

            foreach ($ls->items as $item) {
                /** @var ScheduleItem $item */
                foreach ($item->getSlotDatas() as $data) {
                    $lesson = $data->lesson;
                    if (!$lesson || (int)$lesson->type !== LessonType::INTERNSHIP->value) {
                        continue;
                    }

                    $rows[] = $this->formatScheduleRow($item, $data, false);
                }
            }
        }
    }

    /**
     * Staj dersi için bina ve derslik bilgisini çözümler.
     * Eğer derslik tanımlı değilse programın bağlı olduğu birim binası ve uzem dersliğini döner.
     *
     * @param Lesson $lesson
     * @param object|null $slotData
     * @return array{0: string, 1: string} [bina_adi, derslik_adi]
     */
    protected function resolveBuildingAndClassroomForInternship(Lesson $lesson, ?object $slotData = null): array
    {
        if (!empty($slotData?->classroom)) {
            return [
                $slotData->classroom->building?->name ?? '',
                $slotData->classroom->name ?? '',
            ];
        }

        $cacheKey = ($lesson->program_id ?? 0) . '_' . ($lesson->building_id ?? 0);
        if (isset($this->resolvedInternshipLocationCache[$cacheKey])) {
            return $this->resolvedInternshipLocationCache[$cacheKey];
        }

        // Program -> Department -> Unit hiyerarşisi
        $program    = $lesson->program ?? ($lesson->program_id ? (new Program())->find($lesson->program_id) : null);
        $department = $program?->department ?? ($program?->department_id ? (new Department())->find($program->department_id) : null);
        $unit       = $department ? ($department->unit ?? ($department->unit_id ? (new Unit())->find($department->unit_id) : null)) : null;

        // Birim Binası
        $building = null;
        if (!empty($lesson->building_id)) {
            $building = (new Building())->find($lesson->building_id);
        }
        if (!$building && $unit) {
            $building = (new Building())->get()->where(['unit_id' => $unit->id])->first();
        }

        // Uzem Dersliği
        $uzemClassroom = null;
        if ($building) {
            $classrooms    = (new Classroom())->get()->where(['building_id' => $building->id])->all();
            $uzemClassroom = $this->findUzemClassroom($classrooms);
        }

        if (!$uzemClassroom && $unit) {
            $unitBuildings = (new Building())->get()->where(['unit_id' => $unit->id])->all();
            $buildingIds   = array_filter(array_column($unitBuildings, 'id'));
            if (!empty($buildingIds)) {
                $classrooms    = (new Classroom())->get()->where(['building_id' => ['in' => $buildingIds]])->all();
                $uzemClassroom = $this->findUzemClassroom($classrooms);
                if ($uzemClassroom && !$building) {
                    $building = (new Building())->find($uzemClassroom->building_id);
                }
            }
        }

        $buildingName  = $building?->name ?? '';
        $classroomName = $uzemClassroom?->name ?? 'UZEM';

        $resolved = [$buildingName, $classroomName];
        $this->resolvedInternshipLocationCache[$cacheKey] = $resolved;

        return $resolved;
    }

    /**
     * Verilen derslik listesi içinden UZEM veya uzaktan eğitim tipindeki dersliği bulur.
     *
     * @param array<int, Classroom> $classrooms
     * @return Classroom|null
     */
    protected function findUzemClassroom(array $classrooms): ?Classroom
    {
        foreach ($classrooms as $c) {
            if (stripos($c->name, 'uzem') !== false) {
                return $c;
            }
        }

        foreach ($classrooms as $c) {
            if ((int)$c->type === ClassroomType::REMOTE_EDUCATION->value) {
                return $c;
            }
        }

        return null;
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
