<?php

declare(strict_types=1);

namespace App\Services\Export\Excel;

use App\Models\Lesson;
use App\Models\Program;
use App\Repositories\ProgramRepository;
use App\Services\LessonService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Exception;
use function App\Helpers\getSuggestedSemesterNo;

/**
 * Program ders atamalarını importLessons şablonuna uygun Excel formatında dışa aktarır.
 * Excel çıktısında kullanıcı arayüzü ile uyumlu olarak önerilen yarıyıl (suggested semester) gösterilir;
 * kullanıcı dersi kaydetmediği sürece veritabanındaki asıl yarıyıl bilgisi değişmez.
 */
class LessonAssignmentExcelExporter
{
    /**
     * Tek bir programın ders atamalarını Excel formatında dışa aktarır.
     *
     * @param int $programId
     * @param string $semester
     * @param string $academicYear
     * @param bool $showClassroomType
     * @param bool $showBuilding
     * @return void
     * @throws Exception
     */
    public function export(
        int $programId,
        string $semester,
        string $academicYear,
        bool $showClassroomType = true,
        bool $showBuilding = true
    ): void {
        $this->exportMultiple(
            [$programId],
            $semester,
            $academicYear,
            $showClassroomType,
            $showBuilding
        );
    }

    /**
     * Belirtilen programların ders atamalarını tek bir Excel dosyasında dışa aktarır.
     *
     * @param int[] $programIds
     * @param string $semester
     * @param string $academicYear
     * @param bool $showClassroomType
     * @param bool $showBuilding
     * @param string|null $customFileName
     * @return void
     * @throws Exception
     */
    public function exportMultiple(
        array $programIds,
        string $semester,
        string $academicYear,
        bool $showClassroomType = true,
        bool $showBuilding = true,
        ?string $customFileName = null
    ): void {
        $programRepo = new ProgramRepository();
        $lessonService = new LessonService();

        $allLessonsData = [];

        foreach ($programIds as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) {
                continue;
            }

            $totalSemesters = $programRepo->getProgramTotalSemesters($pid);
            $lessons = $lessonService->getLessonsByProgramAndPeriod($pid, $semester, $academicYear, $totalSemesters);

            foreach ($lessons as $lesson) {
                $displaySemesterNo = getSuggestedSemesterNo((int)$lesson->semester_no, $semester, $totalSemesters);
                $allLessonsData[] = [
                    'lesson'             => $lesson,
                    'suggested_semester' => $displaySemesterNo,
                    'program_name'       => $lesson->program?->name ?? '',
                    'department_name'    => $lesson->department?->name ?? '',
                ];
            }
        }

        // Sıralama: Bölüm Adı -> Program Adı -> Yarıyıl No -> Ders Kodu
        usort($allLessonsData, function (array $a, array $b) {
            $deptCmp = strcmp($a['department_name'], $b['department_name']);
            if ($deptCmp !== 0) {
                return $deptCmp;
            }
            $progCmp = strcmp($a['program_name'], $b['program_name']);
            if ($progCmp !== 0) {
                return $progCmp;
            }
            if ($a['suggested_semester'] !== $b['suggested_semester']) {
                return $a['suggested_semester'] <=> $b['suggested_semester'];
            }
            return strcmp((string)$a['lesson']->code, (string)$b['lesson']->code);
        });

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ders Görevlendirme Listesi');

        // importLessons ile birebir uyumlu başlıklar (Derslik türü ve Bina opsiyonel)
        $headers = [
            "Bölümü / Ana Bilim Dalı",
            "Programı / Bilim Dalı",
            "Yarıyılı",
            "Türü",
            "Dersin Kodu",
            "Grup No",
            "Dersin Adı",
            "Saati",
            "Kontenjan/Mevcut",
            "Hocası"
        ];

        if ($showClassroomType) {
            $headers[] = "Derslik türü";
        }

        if ($showBuilding) {
            $headers[] = "Bina";
        }

        $sheet->fromArray($headers, null, 'A1');

        // Başlık satırını kalın yap
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);

        $rowNumber = 2;
        foreach ($allLessonsData as $item) {
            /** @var Lesson $lesson */
            $lesson = $item['lesson'];
            $displaySemesterNo = $item['suggested_semester'];

            // Grubu olmayan dersler için '0' yazılır (PhpSpreadsheet NULL yapmasın diye string '0' ile sayısal 0 verilir)
            $groupNo = (!empty($lesson->group_no) && (int)$lesson->group_no > 0) ? (int)$lesson->group_no : '0';
            $size = (!empty($lesson->size) && (int)$lesson->size > 0) ? (int)$lesson->size : '0';

            $rowData = [
                $lesson->department?->name ?? '',
                $lesson->program?->name ?? '',
                $displaySemesterNo,
                $lesson->getTypeName(),
                $lesson->code,
                $groupNo,
                $lesson->name,
                $lesson->hours,
                $size,
                $lesson->lecturer?->getFullName() ?? '',
            ];

            if ($showClassroomType) {
                $rowData[] = $lesson->getClassroomTypeName();
            }

            if ($showBuilding) {
                $rowData[] = $lesson->building?->name ?? '';
            }

            $sheet->fromArray($rowData, null, 'A' . $rowNumber);
            $rowNumber++;
        }

        // Sütun genişliklerini otomatik ayarla
        $columnCount = count($headers);
        for ($colIndex = 1; $colIndex <= $columnCount; $colIndex++) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Dosya adı belirleme
        if (!empty($customFileName)) {
            $fileName = $customFileName;
        } elseif (count($programIds) === 1) {
            /** @var Program|null $program */
            $program = $programRepo->find($programIds[0]);
            $programName = $program ? $program->name : 'Program';
            $fileName = sprintf('%s_%s_%s_Ders_Görevlendirme_Listesi.xlsx', $academicYear, $semester, $programName);
        } else {
            $fileName = sprintf('%s_%s_Tum_Programlar_Ders_Görevlendirme_Listesi.xlsx', $academicYear, $semester);
        }

        $fileName = preg_replace('/[^\w\.\-\s]/u', '_', $fileName);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}
