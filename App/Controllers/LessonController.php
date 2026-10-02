<?php

namespace App\Controllers;

use App\Enums\PermissionType;
use App\Enums\UserRole;

use App\Core\Controller;
use App\Models\Lesson;
use App\Models\Program;
use App\Repositories\LessonRepository;
use App\Repositories\ProgramRepository;
use App\Repositories\LessonAssignmentRepository;
use App\Core\Gate;

use App\Validators\LessonValidator;
use App\Services\LessonService;
use App\Middlewares\AuthMiddleware;
use App\Enums\LessonType;
use App\Validators\CombineLessonValidator;
use App\Validators\CombineExamLessonValidator;
use App\Validators\DeleteCombineLessonValidator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Import\LessonImporter;
use App\Services\Import\UbsLessonParser;
use App\Services\Export\Excel\LessonAssignmentExcelExporter;
use App\Enums\ClassroomType;
use App\Core\Database;
use function App\Helpers\getSettingValue;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSuggestedSemesterNo;
use Exception;

class LessonController extends Controller
{
    protected string $table_name = "lessons";

    protected string $modelName = "App\Models\Lesson";

    /**
     * Dersin türünü seçmek için kullanılacak diziyi döner
     * @return string[]
     */
    public function getTypeList(): array
    {
        return LessonType::toArray();
    }

    /**
     * Yarıyıl seçimi yaparken kıllanılacak verileri dizi olarak döner
     * @return array
     */
    public function getSemesterNoList(): array
    {
        $list = [];
        for ($i = 1; $i <= 12; $i++) {
            $list[$i] = "$i. Yarıyıl";
        }
        return $list;
    }

    /**
     * @param ?int $lecturer_id Girildiğinde o kullanıcıya ait derslerin listesini döner
     * @return array
     * @throws Exception
     */
    public function getLessonsList(?int $lecturer_id = null): array
    {
        if (!is_null($lecturer_id)) {
            $asgns = (new LessonAssignmentRepository())->findActiveAssignmentsForLecturer($lecturer_id);
            return array_map(fn($a) => $a->lesson, array_filter($asgns, fn($a) => !is_null($a->lesson)));
        }
        return (new LessonRepository())->findBy([]);
    }



    /**
     * Yeni ders oluşturur (POST /ajax/lesson/add rotası için)
     */
    public function store(array $requestData): array
    {            
            $dto = (new LessonValidator())->getDTO($requestData);
            Gate::authorize(PermissionType::CREATE->value, Lesson::class, "Yeni Ders oluşturma yetkiniz yok", $dto);
            
            (new LessonService())->saveNew($dto);

            return [
                "status" => "success",
                "msg" => "Ders başarıyla oluşturuldu."
            ];
    }

    /**
     * Mevcut dersi günceller (POST /ajax/lesson/update rotası için)
     */
    public function update(array $requestData): array
    {            /** @var Lesson $lessonFromDb */
            $lessonFromDb = clone (new LessonRepository())->find((int)($requestData['id'] ?? 0));
            if (!$lessonFromDb) {
                throw new Exception("Güncellenecek ders bulunamadı.");
            }

            $currentUser = AuthMiddleware::user();
            $activeAssignment = (new LessonAssignmentRepository())->findActiveAssignmentForLesson($lessonFromDb->id);
            $isLecturerOwnLesson = Gate::allowsRole("lecturer", true)
                && $currentUser
                && $activeAssignment
                && $activeAssignment->lecturer_id == $currentUser->id;

            $dto = (new LessonValidator($isLecturerOwnLesson))->getDTO($requestData);

            (new LessonService())->updateLessonData($lessonFromDb->id, $dto, $isLecturerOwnLesson);

            return [
                "status" => "success",
                "msg" => "Ders başarıyla güncellendi."
            ];
    }

    /**
     * Dersi siler (POST /ajax/lesson/delete rotası için)
     */
    public function destroy(array $requestData): array
    {            if (empty($requestData['id'])) {
                throw new Exception("Silinecek ders ID'si belirtilmedi.");
            }

            $lesson = clone (new LessonRepository())->find($requestData['id']);
            if (!$lesson) {
                throw new Exception("Silinecek ders bulunamadı.");
            }

            Gate::authorize(PermissionType::DELETE->value, $lesson, "Ders silme yetkiniz yok");

            (new LessonService())->deleteLesson($lesson);

            return [
                "status" => "success",
                "msg" => "Ders başarıyla silindi."
            ];
    }


    /**
     * Ders birleştirme önizleme — DB değişikliği yapmaz.
     */
    public function previewCombine(array $requestData): array
    {
        Gate::authorize('combine', Lesson::class, "Ders birleştirme yetkiniz yok");
        $dto = (new CombineLessonValidator())->getDTO($requestData);
        return (new LessonService())->previewCombineLesson($dto);
    }

    /**
     * @throws Exception
     */
    public function combine(array $requestData): array
    {
        Gate::authorize('combine', Lesson::class, "Ders birleştirme yetkiniz yok");
        $dto = (new CombineLessonValidator())->getDTO($requestData);
        
        if (!$dto->parentId || !$dto->childId) {
            throw new Exception("Birleştirmek için dersler belirtilmemiş");
        }

        (new LessonService())->combineLesson($dto);

        return [
            "msg"      => "Dersler Başarıyla birleştirildi.",
            "status"   => "success",
            "redirect" => "self"
        ];
    }

    /**
     * @throws Exception
     */
    public function deleteParentLesson(array $requestData): array
    {
        Gate::authorize('combine', Lesson::class, "Ders birleştirmesi kaldırma yetkiniz yok");
        
        $requestData['type'] = 'lesson';
        $dto = (new DeleteCombineLessonValidator())->getDTO($requestData);
        
        if (empty($dto->id)) {
            throw new Exception("Bağlantısı silinecek dersin id numarası belirtilmemiş");
        }

        (new LessonService())->deleteParentLesson($dto);
        
        return [
            "msg" => "Ders birleştirmesi başarıyla kaldırıldı.",
            "status" => "success",
            "redirect" => "self"
        ];
    }

    /**
     * Sınav birleştirme — farklı hocaların derslerini sınav için birleştirir.
     * @throws Exception
     */
    public function combineExamLesson(array $requestData): array
    {
        Gate::authorize('combine', Lesson::class, "Sınav birleştirme yetkiniz yok");
        
        $dto = (new CombineExamLessonValidator())->getDTO($requestData);
        
        if (empty($dto->parentId) || empty($dto->childId)) {
            throw new Exception("Birleştirmek için dersler belirtilmemiş");
        }

        (new LessonService())->combineExamLesson($dto);

        return [
            "msg"      => "Sınavlar Başarıyla birleştirildi.",
            "status"   => "success",
            "redirect" => "self"
        ];
    }

    /**
     * Sınav birleştirme bağlantısını kaldırır.
     */
    public function deleteExamParentLesson(array $requestData): array
    {
        $requestData['type'] = 'exam';
        $dto = (new DeleteCombineLessonValidator())->getDTO($requestData);

        if (empty($dto->id)) {
            throw new Exception("Bağlantısı silinecek dersin id numarası belirtilmemiş");
        }
        Gate::authorize('combine', Lesson::class, "Sınav birleştirmesi kaldırma yetkiniz yok");
        (new LessonService())->deleteExamParentLesson($dto);
        
        return [
            "msg"      => "Sınav birleştirmesi başarıyla kaldırıldı.",
            "status"   => "success",
            "redirect" => "self"
        ];
    }

    /**
     * Sınav birleştirme için aranabilir ders listesi (TomSelect AJAX).
     * Aynı akademik yıl ve dönemdeki dersleri döner.
     */
    public function getExamCombinableLessons(array $requestData): array
    {
        Gate::authorize('combine', Lesson::class, "Sınav birleştirme listesini almak için yetkiniz yok");

        $lessonId = (int) ($requestData['lesson_id'] ?? 0);
        $search = trim($requestData['search'] ?? '');

        $lessons = (new LessonService())->getExamCombinableLessonsForSelect($lessonId, $search);

        return [
            'status'  => 'success',
            'lessons' => $lessons,
        ];
    }

    /**
     * Excel dosyasından dersleri içe aktarır
     * @param array $files Yüklenen dosyalar
     * @param array $requestData Ek veriler
     * @return array
     */
    public function importLessons(array $files, array $requestData): array
    {
        Gate::authorize(PermissionType::CREATE->value, Lesson::class, "Ders içe aktarma yetkiniz yok.");

        $uploadedFile = $this->validateUploadedFile($files);

        $spreadsheet = IOFactory::load($uploadedFile['tmp_name']);
        $importer    = new LessonImporter($spreadsheet, $requestData);
        $result      = $importer->import();

        return [
            'status'         => "success",
            'msg'            => sprintf(
                "%d Ders oluşturuldu,%d Ders güncellendi. %d hatalı kayıt var",
                $result['added'], $result['updated'], $result['errorCount']
            ),
            'errors'         => $result['errors'],
            'addedLessons'   => $result['addedLessons'],
            'updatedLessons' => $result['updatedLessons']
        ];
    }

    /**
     * Seçilen programa ve döneme ait dersleri hoca atamalarıyla birlikte döner.
     *
     * @param array $requestData
     * @return array
     * @throws Exception
     */
    public function getProgramLessonsForAssignment(array $requestData): array
    {
        $programId = (int)($requestData['program_id'] ?? 0);
        $semester = trim((string)($requestData['semester'] ?? ''));
        $academicYear = trim((string)($requestData['academic_year'] ?? ''));

        if ($programId <= 0 || empty($semester) || empty($academicYear)) {
            throw new Exception("Program, dönem ve akademik yıl seçilmelidir.");
        }

        /** @var Program|null $program */
        $program = (new ProgramRepository())->find($programId);
        if (!$program) {
            throw new Exception("Program bulunamadı.");
        }

        Gate::authorize(PermissionType::VIEW->value, $program, "Bu programın derslerini görme yetkiniz yok.");

        $programRepo   = new ProgramRepository();
        $totalSemesters = $programRepo->getProgramTotalSemesters($programId);
        $validSemesters = getSemesterNumbers($semester, $totalSemesters, true);

        $lessons = (new LessonService())->getLessonsByProgramAndPeriod($programId, $semester, $academicYear, $totalSemesters);

        $data = array_map(function (Lesson $lesson) use ($semester, $totalSemesters) {
            $suggestedSemesterNo = getSuggestedSemesterNo((int)$lesson->semester_no, $semester, $totalSemesters);

            return [
                'id'                   => $lesson->id,
                'code'                 => $lesson->code,
                'group_no'             => $lesson->group_no,
                'name'                 => $lesson->name,
                'semester_no'          => $suggestedSemesterNo,
                'type'                 => $lesson->type,
                'type_name'            => $lesson->getTypeName(),
                'size'                 => $lesson->size ?? 0,
                'hours'                => $lesson->hours,
                'lecturer_id'          => $lesson->lecturer?->id ?? null,
                'lecturer_name'        => $lesson->lecturer?->getFullName() ?? null,
                'classroom_type'       => $lesson->classroom_type,
                'classroom_type_name'  => $lesson->getClassroomTypeName(),
                'building_id'          => $lesson->building_id,
                'building_name'        => $lesson->building?->name ?? '—'
            ];
        }, $lessons);

        usort($data, function ($a, $b) {
            if ($a['semester_no'] !== $b['semester_no']) {
                return $a['semester_no'] <=> $b['semester_no'];
            }
            return strcmp((string)$a['code'], (string)$b['code']);
        });

        return [
            'status'          => 'success',
            'lessons'         => $data,
            'valid_semesters' => $validSemesters
        ];
    }

    /**
     * Program derslerini Excel olarak dışa aktarır.
     *
     * @param array $requestData
     * @return void
     * @throws Exception
     */
    public function exportLessonAssignments(array $requestData): void
    {
        $programId = (int)($requestData['program_id'] ?? 0);
        $semester = trim((string)($requestData['semester'] ?? ''));
        $academicYear = trim((string)($requestData['academic_year'] ?? ''));

        if ($programId <= 0 || empty($semester) || empty($academicYear)) {
            throw new Exception("Program, dönem ve akademik yıl seçilmelidir.");
        }

        /** @var Program|null $program */
        $program = (new ProgramRepository())->find($programId);
        if (!$program) {
            throw new Exception("Program bulunamadı.");
        }

        Gate::authorize(PermissionType::VIEW->value, $program, "Bu programın derslerini dışa aktarma yetkiniz yok.");

        $showClassroomType = filter_var($requestData['show_classroom_type'] ?? $requestData['include_classroom_type'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $showBuilding = filter_var($requestData['show_building'] ?? $requestData['include_building'] ?? true, FILTER_VALIDATE_BOOLEAN);

        (new LessonAssignmentExcelExporter())->export(
            $programId,
            $semester,
            $academicYear,
            $showClassroomType,
            $showBuilding
        );
    }

    /**
     * Kullanıcının yetkili olduğu tüm programların ders görevlendirmelerini tek bir Excel dosyasında dışa aktarır.
     *
     * @param array $requestData
     * @return void
     * @throws Exception
     */
    public function exportAllLessonAssignments(array $requestData): void
    {
        $semester = trim((string)($requestData['semester'] ?? ''));
        $academicYear = trim((string)($requestData['academic_year'] ?? ''));

        if (empty($semester) || empty($academicYear)) {
            throw new Exception("Dönem ve akademik yıl seçilmelidir.");
        }

        Gate::authorizeRole(UserRole::DepartmentHead->value, false, "Tüm programların ders atamalarını dışa aktarma yetkiniz yok.");

        $currentUser = AuthMiddleware::user();
        if (!$currentUser) {
            throw new Exception("Oturum açmış kullanıcı bulunamadı.");
        }

        $programRepo = new ProgramRepository();
        if ($currentUser->role === UserRole::DepartmentHead->value && !empty($currentUser->department_id)) {
            $programs = $programRepo->getAuthorized('view', ['department_id' => $currentUser->department_id, 'active' => true], ['department']);
        } else {
            $programs = $programRepo->getAuthorized('view', ['active' => true], ['department']);
        }

        if (empty($programs)) {
            throw new Exception("Dışa aktarılacak yetkili program bulunamadı.");
        }

        $programIds = array_map(fn(Program $p) => (int)$p->id, $programs);

        $showClassroomType = filter_var($requestData['show_classroom_type'] ?? $requestData['include_classroom_type'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $showBuilding = filter_var($requestData['show_building'] ?? $requestData['include_building'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $customFileName = null;
        if ($currentUser->role === UserRole::DepartmentHead->value && !empty($programs[0]->department?->name)) {
            $customFileName = sprintf('%s_%s_%s_Tum_Programlar_Ders_Görevlendirme_Listesi.xlsx', $academicYear, $semester, $programs[0]->department->name);
        }

        (new LessonAssignmentExcelExporter())->exportMultiple(
            $programIds,
            $semester,
            $academicYear,
            $showClassroomType,
            $showBuilding,
            $customFileName
        );
    }

    /**
     * Tek bir dersin mevcudunu, saatini ve hoca atamasını günceller.
     *
     * @param array $requestData
     * @return array
     * @throws Exception
     */
    public function updateAssignment(array $requestData): array
    {
        $dto = (new LessonValidator(isAssignmentUpdate: true))->getDTO($requestData);

        (new LessonService())->updateLessonData((int)$dto->id, $dto, false);

        return [
            'status' => 'success',
            'msg' => 'Ders ataması başarıyla kaydedildi.'
        ];
    }

    /**
     * Birden fazla dersin atamasını toplu kaydeder.
     *
     * @param array $requestData
     * @return array
     * @throws Exception
     */
    public function bulkUpdateAssignments(array $requestData): array
    {
        $assignments = $requestData['assignments'] ?? [];
        if (!is_array($assignments)) {
            throw new Exception("Geçersiz veri formatı.");
        }

        $semester = trim((string)($requestData['semester'] ?? getSettingValue('semester')));
        $academicYear = trim((string)($requestData['academic_year'] ?? getSettingValue('academic_year')));

        $updatedCount = 0;
        foreach ($assignments as $item) {
            $item['semester'] = $semester;
            $item['academic_year'] = $academicYear;
            $this->updateAssignment($item);
            $updatedCount++;
        }

        return [
            'status' => 'success',
            'msg' => "{$updatedCount} ders ataması başarıyla kaydedildi."
        ];
    }

    /**
     * Yüklenen UBS Excel dosyasını seçilen program için analiz eder ve ders listesini döner.
     *
     * @param array $files
     * @param array $requestData
     * @return array
     * @throws Exception
     */
    public function parseUbsLessons(array $files, array $requestData): array
    {
        $programId = (int)($requestData['program_id'] ?? 0);
        $semester = trim((string)($requestData['semester'] ?? getSettingValue('semester')));
        $academicYear = trim((string)($requestData['academic_year'] ?? getSettingValue('academic_year')));

        if ($programId <= 0) {
            throw new Exception("Lütfen bir program seçiniz.");
        }

        $program = (new ProgramRepository())->find($programId);
        if (!$program) {
            throw new Exception("Seçilen program bulunamadı.");
        }

        Gate::authorize(PermissionType::LIST->value, Lesson::class, "Dersleri inceleme yetkiniz yok.");

        $uploadedFile = $files['importFile'] ?? $files['file'] ?? null;
        if (!$uploadedFile || empty($uploadedFile['tmp_name'])) {
            throw new Exception("Excel dosyası yüklenmedi.");
        }

        // Dosya uzantısı kontrolü
        $ext = strtolower(pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            throw new Exception("Yalnızca .xlsx formatındaki dosyalar desteklenmektedir.");
        }

        $spreadsheet = IOFactory::load($uploadedFile['tmp_name']);
        $parser = new UbsLessonParser();
        $result = $parser->parse($spreadsheet, $programId, $semester, $academicYear);

        return [
            'status' => 'success',
            'data'   => $result,
        ];
    }

    /**
     * UBS tablosundan gelen tek bir dersi kaydeder veya günceller.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function saveUbsLessonItem(array $data): array
    {
        $programId = (int)($data['program_id'] ?? 0);
        if ($programId <= 0) {
            throw new Exception("Geçerli bir program seçilmelidir.");
        }

        /** @var Program|null $program */
        $program = (new ProgramRepository())->find($programId);
        if (!$program) {
            throw new Exception("Program bulunamadı.");
        }

        $lessonId = !empty($data['lesson_id']) ? (int)$data['lesson_id'] : null;
        $semester = trim((string)($data['semester'] ?? getSettingValue('semester')));
        $academicYear = trim((string)($data['academic_year'] ?? getSettingValue('academic_year')));
        $lecturerId = !empty($data['lecturer_id']) ? (int)$data['lecturer_id'] : null;

        $lessonService = new LessonService();

        if ($lessonId) {
            /** @var Lesson|null $lesson */
            $lesson = (new Lesson())->find($lessonId);
            if (!$lesson) {
                throw new Exception("Güncellenecek ders bulunamadı (ID: {$lessonId}).");
            }

            Gate::authorize(PermissionType::UPDATE->value, $lesson, "Bu dersi güncelleme yetkiniz yok.");

            // Mevcut dersi güncelle
            $lessonData = [
                'id'             => $lesson->id,
                'code'           => strtoupper(trim((string)($data['code'] ?? $lesson->code))),
                'group_no'       => isset($data['group_no']) ? (int)$data['group_no'] : (int)$lesson->group_no,
                'name'           => trim((string)($data['name'] ?? $lesson->name)),
                'size'           => isset($data['size']) ? (int)$data['size'] : (int)$lesson->size,
                'hours'          => isset($data['hours']) ? (int)$data['hours'] : (int)$lesson->hours,
                'type'           => isset($data['type']) ? (int)$data['type'] : (int)$lesson->type,
                'semester_no'    => isset($data['semester_no']) ? (int)$data['semester_no'] : (int)$lesson->semester_no,
                'classroom_type' => isset($data['classroom_type']) ? (int)$data['classroom_type'] : (int)$lesson->classroom_type,
                'building_id'    => !empty($data['building_id']) ? (int)$data['building_id'] : $lesson->building_id,
                'department_id'  => $lesson->department_id,
                'program_id'     => $lesson->program_id,
                'semester'       => $semester,
                'academic_year'  => $academicYear,
                'lecturer_id'    => $lecturerId,
            ];

            $dto = (new LessonValidator(isAssignmentUpdate: false))->getDTO($lessonData);
            $lessonService->updateLessonData($lesson->id, $dto, false);

            return [
                'status'    => 'success',
                'msg'       => "'{$lesson->name}' dersi başarıyla güncellendi.",
                'lesson_id' => $lesson->id,
                'type'      => 'updated',
            ];
        } else {
            Gate::authorize(PermissionType::CREATE->value, Lesson::class, "Yeni ders ekleme yetkiniz yok.");

            $lessonData = [
                'code'           => strtoupper(trim((string)($data['code'] ?? ''))),
                'group_no'       => (int)($data['group_no'] ?? 0),
                'name'           => trim((string)($data['name'] ?? '')),
                'size'           => (int)($data['size'] ?? 0),
                'hours'          => (int)($data['hours'] ?? 0),
                'type'           => (int)($data['type'] ?? LessonType::COMPULSORY->value),
                'semester_no'    => (int)($data['semester_no'] ?? 1),
                'classroom_type' => (int)($data['classroom_type'] ?? ClassroomType::CLASSROOM->value),
                'building_id'    => !empty($data['building_id']) ? (int)$data['building_id'] : null,
                'department_id'  => (int)$program->department_id,
                'program_id'     => (int)$program->id,
                'semester'       => $semester,
                'academic_year'  => $academicYear,
                'lecturer_id'    => $lecturerId,
            ];

            $dto = (new LessonValidator())->getDTO($lessonData);
            $newLessonId = $lessonService->saveNew($dto);

            return [
                'status'    => 'success',
                'msg'       => "'{$dto->name}' yeni ders olarak başarıyla eklendi.",
                'lesson_id' => $newLessonId,
                'type'      => 'added',
            ];
        }
    }

    /**
     * UBS tablosundaki tüm veya seçili dersleri topluca kaydeder.
     *
     * @param array $requestData
     * @return array
     * @throws Exception
     */
    public function bulkSaveUbsLessons(array $requestData): array
    {
        $items = $requestData['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            throw new Exception("Kaydedilecek ders verisi bulunamadı.");
        }

        $programId = (int)($requestData['program_id'] ?? 0);
        $semester = trim((string)($requestData['semester'] ?? getSettingValue('semester')));
        $academicYear = trim((string)($requestData['academic_year'] ?? getSettingValue('academic_year')));

        if ($programId <= 0) {
            throw new Exception("Geçerli bir program seçilmelidir.");
        }

        $addedCount = 0;
        $updatedCount = 0;
        $errors = [];

        Database::transaction(function () use ($items, $programId, $semester, $academicYear, &$addedCount, &$updatedCount, &$errors) {
            foreach ($items as $idx => $item) {
                $item['program_id'] = $programId;
                $item['semester'] = $semester;
                $item['academic_year'] = $academicYear;

                try {
                    $res = $this->saveUbsLessonItem($item);
                    if ($res['type'] === 'added') {
                        $addedCount++;
                    } else {
                        $updatedCount++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Ders '" . ($item['code'] ?? '') . " - " . ($item['name'] ?? '') . "': " . $e->getMessage();
                }
            }

            if (!empty($errors) && ($addedCount + $updatedCount) === 0) {
                throw new Exception("İşlemler sırasında hata oluştu:\n" . implode("\n", $errors));
            }
        });

        return [
            'status'       => 'success',
            'added'        => $addedCount,
            'updated'      => $updatedCount,
            'errors'       => $errors,
            'msg'          => "{$updatedCount} ders güncellendi, {$addedCount} yeni ders eklendi." . (!empty($errors) ? " (" . count($errors) . " ders atlandı)" : ""),
        ];
    }
}
