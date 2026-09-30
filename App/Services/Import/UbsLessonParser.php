<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\ClassroomType;
use App\Enums\LessonType;
use App\Enums\UserTitle;
use App\Models\Building;
use App\Models\Lesson;
use App\Models\Program;
use App\Repositories\BuildingRepository;
use App\Repositories\LessonAssignmentRepository;
use App\Repositories\LessonRepository;
use App\Repositories\ProgramRepository;
use App\Repositories\UserRepository;
use App\Services\LessonService;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Üniversite Bilgi Sistemi (UBS / ÖBS) ham Excel çıktılarını ayrıştırıp
 * sisteme aktarılmak üzere yapılandıran servis sınıfı.
 */
class UbsLessonParser
{
    private UserRepository $userRepository;
    private ProgramRepository $programRepository;
    private BuildingRepository $buildingRepository;

    /**
     * Hoca arama önbelleği
     * @var array<string, int|false>
     */
    private array $lecturerCache = [];

    public function __construct(
        ?UserRepository $userRepository = null,
        ?ProgramRepository $programRepository = null,
        ?BuildingRepository $buildingRepository = null
    ) {
        $this->userRepository = $userRepository ?? new UserRepository();
        $this->programRepository = $programRepository ?? new ProgramRepository();
        $this->buildingRepository = $buildingRepository ?? new BuildingRepository();
    }

    /**
     * Akademik unvan yazımlarını sistemdeki standart unvanlara dönüştürür.
     * Örneğin: "öğretim görevlisi" -> "Öğr. Gör.", "Dr. Öğretim Üyesi" -> "Dr. Öğr. Üyesi"
     */
    public static function normalizeAcademicTitle(string $fullName): string
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return '';
        }

        $patterns = [
            '/^\s*öğretim\s+görevlisi\s+/ui' => 'Öğr. Gör. ',
            '/^\s*öğr\.\s*gör\.\s+/ui'       => 'Öğr. Gör. ',
            '/^\s*dr\.\s*öğretim\s+üyesi\s+/ui' => 'Dr. Öğr. Üyesi ',
            '/^\s*dr\.\s*öğr\.\s*üyesi\s+/ui'   => 'Dr. Öğr. Üyesi ',
            '/^\s*doç\.\s*dr\.\s+/ui'          => 'Doç. Dr. ',
            '/^\s*prof\.\s*dr\.\s+/ui'         => 'Prof. Dr. ',
            '/^\s*arş\.\s*gör\.\s*dr\.\s+/ui'  => 'Arş. Gör. Dr. ',
            '/^\s*arş\.\s*gör\.\s+/ui'       => 'Arş. Gör. ',
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $fullName)) {
                $fullName = (string) preg_replace($pattern, $replacement, $fullName, 1);
                break;
            }
        }

        // Çift boşlukları tek boşluğa indir
        return (string) preg_replace('/\s+/', ' ', trim($fullName));
    }

    /**
     * Ders saatini Teorik, Uygulama ve Laboratuvar değerlerini toplayarak hesaplar.
     * 
     * // TODO: İlerleyen süreçte haftalık ders saati kırılımları (T: Teorik, U: Uygulama, L: Laboratuvar)
     * // veritabanı ve model katmanında ayrı sütunlar olarak tutulacak ve burada da ayrı alanlara atanacaktır.
     */
    public static function calculateTotalHours(int $t, int $u, int $l): int
    {
        return max(0, $t) + max(0, $u) + max(0, $l);
    }

    /**
     * Yüklenen UBS Spreadsheet nesnesini seçili program ve dönem için ayrıştırır.
     *
     * @param Spreadsheet $spreadsheet
     * @param int $programId
     * @param string $semester
     * @param string $academicYear
     * @return array{
     *     success: bool,
     *     program: array{id: int, name: string, department_id: int},
     *     total_raw_rows: int,
     *     skipped_no_lecturer: int,
     *     existing_count: int,
     *     new_count: int,
     *     items: array<int, array<string, mixed>>
     * }
     * @throws Exception
     */
    public function parse(Spreadsheet $spreadsheet, int $programId, string $semester, string $academicYear): array
    {
        /** @var Program|null $program */
        $program = $this->programRepository->find($programId);
        if (!$program) {
            throw new Exception("Seçilen program bulunamadı!");
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            throw new Exception("Excel dosyasında geçerli veri bulunamadı.");
        }

        // Başlık satırını bul (Genelde Satır 1 veya Satır 2)
        $headerRowIndex = null;
        foreach ($rows as $rIdx => $row) {
            foreach ($row as $cell) {
                if (is_string($cell) && mb_stripos($cell, 'Ders Kodu') !== false) {
                    $headerRowIndex = $rIdx;
                    break 2;
                }
            }
        }

        if ($headerRowIndex === null) {
            throw new Exception("Excel dosyasında 'Ders Kodu' sütun başlığı bulunamadı. Lütfen geçerli bir UBS dosyası yükleyin.");
        }

        // Sütun indekslerini belirle
        $headerRow = $rows[$headerRowIndex];
        $colIndexes = [
            'code'           => null,
            'group_no'       => null,
            'name'           => null,
            't'              => null,
            'u'              => null,
            'l'              => null,
            'size'           => null,
            'lecturer_name'  => null,
        ];

        foreach ($headerRow as $cIdx => $cVal) {
            if (!is_string($cVal)) continue;
            $cleanHeader = trim($cVal);

            if (mb_stripos($cleanHeader, 'Ders Kodu') !== false) {
                $colIndexes['code'] = $cIdx;
            } elseif (mb_stripos($cleanHeader, 'Grup No') !== false) {
                $colIndexes['group_no'] = $cIdx;
            } elseif (mb_stripos($cleanHeader, 'Ders Adı') !== false) {
                $colIndexes['name'] = $cIdx;
            } elseif (mb_strtolower($cleanHeader) === 't') {
                $colIndexes['t'] = $cIdx;
            } elseif (mb_strtolower($cleanHeader) === 'u') {
                $colIndexes['u'] = $cIdx;
            } elseif (mb_strtolower($cleanHeader) === 'l') {
                $colIndexes['l'] = $cIdx;
            } elseif (mb_stripos($cleanHeader, 'Mevcut') !== false) {
                $colIndexes['size'] = $cIdx;
            } elseif (mb_stripos($cleanHeader, 'Öğretim Üyesi') !== false && mb_stripos($cleanHeader, 'Kimlik') === false) {
                $colIndexes['lecturer_name'] = $cIdx;
            }
        }

        if ($colIndexes['code'] === null || $colIndexes['name'] === null) {
            throw new Exception("Ders Kodu veya Ders Adı sütunları tespit edilemedi.");
        }

        // 1. Aşama: Ham satırları topla ve ders kodlarının geçiş sıklığını (frekansını) hesapla
        $validRawRows = [];
        $codeCounts = [];

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $code = isset($colIndexes['code']) && isset($r[$colIndexes['code']]) ? trim((string)$r[$colIndexes['code']]) : '';
            $name = isset($colIndexes['name']) && isset($r[$colIndexes['name']]) ? trim((string)$r[$colIndexes['name']]) : '';

            if ($code === '' && $name === '') {
                continue; // Tamamen boş satır
            }

            $rawLecturer = isset($colIndexes['lecturer_name']) && isset($r[$colIndexes['lecturer_name']])
                ? trim((string)$r[$colIndexes['lecturer_name']])
                : '';

            // Kural: Hocası atanmamış satırları otomatik atla
            if ($rawLecturer === '' || mb_stripos($rawLecturer, 'atanmamış') !== false) {
                continue;
            }

            $codeUpper = mb_strtoupper($code, 'UTF-8');
            $codeCounts[$codeUpper] = ($codeCounts[$codeUpper] ?? 0) + 1;

            $validRawRows[] = [
                'excel_row' => $i + 1,
                'code'      => $codeUpper,
                'raw_group' => isset($colIndexes['group_no']) && isset($r[$colIndexes['group_no']]) ? (int)$r[$colIndexes['group_no']] : 1,
                'name'      => $name,
                't'         => isset($colIndexes['t']) && isset($r[$colIndexes['t']]) ? (int)$r[$colIndexes['t']] : 0,
                'u'         => isset($colIndexes['u']) && isset($r[$colIndexes['u']]) ? (int)$r[$colIndexes['u']] : 0,
                'l'         => isset($colIndexes['l']) && isset($r[$colIndexes['l']]) ? (int)$r[$colIndexes['l']] : 0,
                'size'      => isset($colIndexes['size']) && isset($r[$colIndexes['size']]) ? (int)$r[$colIndexes['size']] : 0,
                'raw_lecturer' => $rawLecturer,
            ];
        }

        // 2. Aşama: Seçili programa ve seçili döneme (Güz / Bahar) ait mevcut dersleri çek
        $lessonService = new LessonService();
        /** @var Lesson[] $existingLessons */
        $existingLessons = $lessonService->getLessonsByProgramAndPeriod($programId, $semester, $academicYear);

        /** @var array<string, Lesson> $lessonMap key: "CODE_GROUPNO" */
        $lessonMap = [];
        foreach ($existingLessons as $l) {
            $key = mb_strtoupper($l->code ?? '', 'UTF-8') . '_' . ((int)($l->group_no ?? 0));
            $lessonMap[$key] = $l;
        }

        // Mevcut dönem atamalarını çek (hoca bilgisi için)
        $assignmentRepo = new LessonAssignmentRepository();

        $items = [];
        $matchedDbLessonIds = [];
        $existingCount = 0;
        $newCount = 0;

        foreach ($validRawRows as $raw) {
            $code = $raw['code'];

            // Kural: Eğer aynı kodda dosyada tek bir kayıt varsa grup 0 kabul edilir,
            // birden fazla kayıt varsa dosyadaki grup numarası kullanılır.
            $finalGroupNo = ($codeCounts[$code] === 1) ? 0 : $raw['raw_group'];

            // Saat hesaplama
            $hours = self::calculateTotalHours($raw['t'], $raw['u'], $raw['l']);

            // Hoca normalizasyonu ve eşleştirmesi
            $normalizedLecturerName = self::normalizeAcademicTitle($raw['raw_lecturer']);
            $matchedUser = $this->findLecturer($normalizedLecturerName);

            // Veritabanında ara: program_id + code + group_no
            $lookupKey = $code . '_' . $finalGroupNo;
            $dbLesson = $lessonMap[$lookupKey] ?? null;

            // Eğer kod eşleşti ama grup 0 bulunamadıysa ve dosyada tek kayıt varsa, 
            // veritabanında code + group_no=1 kontrolü de yapalım (eski kayıt uyumluluğu için)
            if (!$dbLesson && $finalGroupNo === 0 && isset($lessonMap[$code . '_1'])) {
                $dbLesson = $lessonMap[$code . '_1'];
                $finalGroupNo = 1;
            }

            if ($dbLesson) {
                $existingCount++;
                $matchedDbLessonIds[$dbLesson->id] = true;
                // Mevcut atanan hocayı al
                $currentAssignment = $assignmentRepo->findByLessonAndPeriod($dbLesson->id, $semester, $academicYear);
                $currentLecturerId = $currentAssignment ? (int)$currentAssignment->lecturer_id : null;

                $items[] = [
                    'status'             => 'existing', // Sistemde mevcut
                    'status_label'       => 'Mevcut Ders',
                    'excel_row'          => $raw['excel_row'],
                    'lesson_id'          => $dbLesson->id,
                    'code'               => $dbLesson->code,
                    'group_no'           => (int)$dbLesson->group_no,
                    'name'               => $dbLesson->name,
                    'raw_name'           => $raw['name'],
                    'hours'              => $hours > 0 ? $hours : (int)$dbLesson->hours,
                    'db_hours'           => (int)$dbLesson->hours,
                    'size'               => $raw['size'] > 0 ? $raw['size'] : (int)$dbLesson->size,
                    'db_size'            => (int)$dbLesson->size,
                    'semester_no'        => (int)($dbLesson->semester_no ?? 1),
                    'type'               => (int)($dbLesson->type ?? LessonType::COMPULSORY->value),
                    'classroom_type'     => (int)($dbLesson->classroom_type ?? ClassroomType::CLASSROOM->value),
                    'building_id'        => (int)($dbLesson->building_id ?? 0),
                    'lecturer_id'        => $matchedUser ? $matchedUser->id : $currentLecturerId,
                    'lecturer_name'      => $matchedUser ? $matchedUser->getFullName() : $raw['raw_lecturer'],
                    'raw_lecturer'       => $raw['raw_lecturer'],
                    'lecturer_matched'   => $matchedUser !== null,
                    'is_conflict'        => false,
                ];
            } else {
                $newCount++;
                // Yeni ders: Tahmin edilen yarıyıl ve ders türü
                $guessedSemesterNo = self::guessSemesterNo($code, $semester);
                $guessedType = self::guessLessonType($code);

                $items[] = [
                    'status'             => 'new', // Sistemde yok, yeni eklenecek
                    'status_label'       => 'Yeni Ders',
                    'excel_row'          => $raw['excel_row'],
                    'lesson_id'          => null,
                    'code'               => $code,
                    'group_no'           => $finalGroupNo,
                    'name'               => $raw['name'],
                    'raw_name'           => $raw['name'],
                    'hours'              => $hours,
                    'db_hours'           => null,
                    'size'               => $raw['size'],
                    'db_size'            => null,
                    'semester_no'        => $guessedSemesterNo,
                    'type'               => $guessedType->value,
                    'classroom_type'     => ClassroomType::CLASSROOM->value,
                    'building_id'        => null,
                    'lecturer_id'        => $matchedUser ? $matchedUser->id : null,
                    'lecturer_name'      => $matchedUser ? $matchedUser->getFullName() : $raw['raw_lecturer'],
                    'raw_lecturer'       => $raw['raw_lecturer'],
                    'lecturer_matched'   => $matchedUser !== null,
                    'is_conflict'        => true, // Vurgulanması gereken durum
                ];
            }
        }

        // 3. Aşama: Sistemde kayıtlı olup UBS dosyasında hiç yer almayan dersleri tespit et
        $missingInUbsCount = 0;
        foreach ($existingLessons as $dbL) {
            if (!isset($matchedDbLessonIds[$dbL->id])) {
                $missingInUbsCount++;
                $currentAssignment = $assignmentRepo->findByLessonAndPeriod($dbL->id, $semester, $academicYear);
                $curLecId = $currentAssignment ? (int)$currentAssignment->lecturer_id : null;
                /** @var \App\Models\User|null $curLecUser */
                $curLecUser = $curLecId ? $this->userRepository->find($curLecId) : null;

                $items[] = [
                    'status'             => 'missing_in_ubs', // Sistemde var ama UBS dosyasında yok
                    'status_label'       => 'UBS\'de Yok',
                    'excel_row'          => null,
                    'lesson_id'          => $dbL->id,
                    'code'               => $dbL->code,
                    'group_no'           => (int)$dbL->group_no,
                    'name'               => $dbL->name,
                    'raw_name'           => $dbL->name,
                    'hours'              => (int)$dbL->hours,
                    'db_hours'           => (int)$dbL->hours,
                    'size'               => (int)$dbL->size,
                    'db_size'            => (int)$dbL->size,
                    'semester_no'        => (int)($dbL->semester_no ?? 1),
                    'type'               => (int)($dbL->type ?? LessonType::COMPULSORY->value),
                    'classroom_type'     => (int)($dbL->classroom_type ?? ClassroomType::CLASSROOM->value),
                    'building_id'        => (int)($dbL->building_id ?? 0),
                    'lecturer_id'        => $curLecId,
                    'lecturer_name'      => $curLecUser ? $curLecUser->getFullName() : '',
                    'raw_lecturer'       => '',
                    'lecturer_matched'   => $curLecUser !== null,
                    'is_conflict'        => false,
                ];
            }
        }

        // Yarıyıl ve Ders Koduna göre sırala
        usort($items, function ($a, $b) {
            $semA = $a['semester_no'] ?? 0;
            $semB = $b['semester_no'] ?? 0;
            if ($semA !== $semB) {
                return $semA <=> $semB;
            }
            return strcmp($a['code'] ?? '', $b['code'] ?? '');
        });

        return [
            'success'              => true,
            'program'              => [
                'id'            => $program->id,
                'name'          => $program->name,
                'department_id' => $program->department_id,
            ],
            'total_raw_rows'       => count($rows) - ($headerRowIndex + 1),
            'skipped_no_lecturer'  => (count($rows) - ($headerRowIndex + 1)) - count($validRawRows),
            'existing_count'       => $existingCount,
            'new_count'            => $newCount,
            'missing_in_ubs_count' => $missingInUbsCount,
            'items'                => $items,
        ];
    }

    /**
     * Akademisyen ismine göre kullanıcıyı bulur (Önbellekli)
     */
    private function findLecturer(string $normalizedName): ?\App\Models\User
    {
        if ($normalizedName === '') {
            return null;
        }

        if (array_key_exists($normalizedName, $this->lecturerCache)) {
            $cachedId = $this->lecturerCache[$normalizedName];
            return $cachedId ? $this->userRepository->find($cachedId) : null;
        }

        $user = $this->userRepository->findByFullName($normalizedName);
        if (!$user) {
            // Unvanı kaldırarak isim-soyad aramayı dene
            $parsed = UserTitle::parseAcademicName($normalizedName);
            if (!empty($parsed['name']) && !empty($parsed['last_name'])) {
                $user = $this->userRepository->findOneBy([
                    'name' => $parsed['name'],
                    'surname' => $parsed['last_name']
                ]);
            }
        }

        $this->lecturerCache[$normalizedName] = $user ? $user->id : false;
        return $user;
    }

    /**
     * Ders kodundan ve mevcut dönemden yarıyıl tahmini yapar.
     * Örneğin ders kodunda -1xx varsa 1. Sınıf (Güz ise 1, Bahar ise 2)
     * -2xx varsa 2. Sınıf (Güz ise 3, Bahar ise 4)
     */
    public static function guessSemesterNo(string $code, string $semester): int
    {
        $isGuz = (mb_stripos($semester, 'Güz') !== false);
        
        // Kod içinde 1xx, 2xx, 3xx, 4xx ara (Örn: EEÜİD-121, MAT-117, SEC-281)
        if (preg_match('/(?:-|\b)([1-4])\d{2}\b/u', $code, $matches)) {
            $grade = (int)$matches[1]; // 1, 2, 3, 4. sınıf
            return $isGuz ? (($grade - 1) * 2 + 1) : (($grade - 1) * 2 + 2);
        }

        return $isGuz ? 1 : 2;
    }

    /**
     * Ders kodundan ders türünü tahmin eder.
     * Kodda SEC geçiyorsa Seçmeli, UYG geçiyorsa Staj, yoksa Zorunlu.
     */
    public static function guessLessonType(string $code): LessonType
    {
        $codeUpper = mb_strtoupper($code, 'UTF-8');
        if (str_contains($codeUpper, 'SEC')) {
            return LessonType::ELECTIVE;
        }
        if (str_contains($codeUpper, 'UYG') || str_contains($codeUpper, 'STAJ')) {
            return LessonType::INTERNSHIP;
        }
        return LessonType::COMPULSORY;
    }
}
