<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Lesson;
use App\Repositories\LessonRepository;
use App\Core\Gate;
use App\DTOs\LessonDTO;
use App\Validators\LessonValidator;
use App\Services\LessonService;
use App\Middlewares\AuthMiddleware;
use App\Enums\LessonType;
use App\DTOs\CombineLessonDTO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Import\LessonImporter;
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
        $list = [];
        foreach (LessonType::cases() as $case) {
            $list[$case->value] = $case->label();
        }
        return $list;
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
        $filters = [];
        if (!is_null($lecturer_id))
            $filters["lecturer_id"] = $lecturer_id;
        return (new LessonRepository())->findBy($filters);
    }

    /**
     * Yeni ders oluşturur (POST /ajax/lesson/add rotası için)
     */
    public function store(array $requestData): array
    {
        try {
            $lesson = new Lesson();
            Gate::authorize("create", $lesson, "Yeni Ders oluşturma yetkiniz yok");

            $validator = new LessonValidator();
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            $dto = LessonDTO::fromArray($requestData);
            
            $lesson->fill($dto->toArray());
            (new LessonService())->saveNew($lesson);

            return [
                "status" => "success",
                "msg" => "Ders başarıyla oluşturuldu."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Mevcut dersi günceller (POST /ajax/lesson/update rotası için)
     */
    public function update(array $requestData): array
    {
        try {
            $lessonFromDb = (new Lesson())->find((int)($requestData['id'] ?? 0));
            if (!$lessonFromDb) {
                throw new Exception("Güncellenecek ders bulunamadı.");
            }

            $currentUser = AuthMiddleware::user();
            $isLecturerOwnLesson = Gate::allowsRole("lecturer", true)
                && $currentUser
                && $lessonFromDb->lecturer_id == $currentUser->id;

            $validator = new LessonValidator($isLecturerOwnLesson);
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            if ($isLecturerOwnLesson) {
                Gate::authorize("update", $lessonFromDb, "Ders güncelleme yetkiniz yok");
                
                $lessonFromDb->size = (int)($requestData['size'] ?? 0);
                if (isset($requestData['classroom_type']) && $requestData['classroom_type'] !== '') {
                    $lessonFromDb->classroom_type = (int)$requestData['classroom_type'];
                }
                
                (new LessonService())->updateLesson($lessonFromDb);
            } else {
                Gate::authorize("update", $lessonFromDb, "Ders güncelleme yetkiniz yok");

                $dto = LessonDTO::fromArray($requestData);
                $lessonFromDb->fill($dto->toArray());
                (new LessonService())->updateLesson($lessonFromDb);
            }

            return [
                "status" => "success",
                "msg" => "Ders başarıyla güncellendi."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Dersi siler (POST /ajax/lesson/delete rotası için)
     */
    public function destroy(array $requestData): array
    {
        try {
            if (empty($requestData['id'])) {
                throw new Exception("Silinecek ders ID'si belirtilmedi.");
            }

            $lesson = clone (new Lesson())->find($requestData['id']);
            if (!$lesson) {
                throw new Exception("Silinecek ders bulunamadı.");
            }

            Gate::authorize("delete", $lesson, "Ders silme yetkiniz yok");

            (new LessonService())->deleteLesson($lesson);

            return [
                "status" => "success",
                "msg" => "Ders başarıyla silindi."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }


    /**
     * Ders birleştirme önizleme — DB değişikliği yapmaz.
     */
    public function previewCombine(array $requestData): array
    {
        try {
            Gate::authorizeRole("submanager", false, "Ders birleştirme yetkiniz yok");
            $dto = CombineLessonDTO::fromArray($requestData);
            return (new LessonService())->previewCombineLesson($dto);
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * @throws Exception
     */
    public function combine(array $requestData): array
    {
        try {
            Gate::authorizeRole("submanager", false, "Ders birleştirme yetkiniz yok");
            $dto = CombineLessonDTO::fromArray($requestData);
            
            if (!$dto->parentId || !$dto->childId) {
                throw new Exception("Birleştirmek için dersler belirtilmemiş");
            }

            (new LessonService())->combineLesson(
                $dto->parentId,
                $dto->childId,
                $dto->getParsedItemsToRemove()
            );

            return [
                "msg"      => "Dersler Başarıyla birleştirildi.",
                "status"   => "success",
                "redirect" => "self"
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * @throws Exception
     */
    public function deleteParentLesson(array $requestData): array
    {
        try {
            Gate::authorizeRole("submanager", false, "Ders birşeltirmesi kaldırma yetkiniz yok");
            
            if (empty($requestData['id'])) {
                throw new Exception("Bağlantısı silinecek dersin id numarası belirtilmemiş");
            }

            (new LessonService())->deleteParentLesson((int) $requestData['id']);
            
            return [
                "msg" => "Ders birleştirmesi başarıyla kaldırıldı.",
                "status" => "success",
                "redirect" => "self"
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Sınav birleştirme — farklı hocaların derslerini sınav için birleştirir.
     * @throws Exception
     */
    public function combineExamLesson(array $requestData): array
    {
        try {
            Gate::authorizeRole("department_head", false, "Sınav birleştirme yetkiniz yok");
            
            if (empty($requestData['parent_lesson_id']) || empty($requestData['child_lesson_id'])) {
                throw new Exception("Birleştirmek için dersler belirtilmemiş");
            }

            (new LessonService())->combineExamLesson(
                (int) $requestData['parent_lesson_id'],
                (int) $requestData['child_lesson_id']
            );

            return [
                "msg"      => "Sınavlar Başarıyla birleştirildi.",
                "status"   => "success",
                "redirect" => "self"
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Sınav birleştirme bağlantısını kaldırır.
     */
    public function deleteExamParentLesson(array $requestData): array
    {
        try {
            if (!key_exists("id", $requestData)) {
                throw new Exception("Bağlantısı silinecek dersin id numarası belirtilmemiş");
            }
            Gate::authorizeRole("department_head", false, "Sınav birleştirmesi kaldırma yetkiniz yok");
            (new LessonService())->deleteExamParentLesson((int) $requestData['id']);
            
            return [
                "msg"      => "Sınav birleştirmesi başarıyla kaldırıldı.",
                "status"   => "success",
                "redirect" => "self"
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Sınav birleştirme için aranabilir ders listesi (TomSelect AJAX).
     * Aynı akademik yıl ve dönemdeki dersleri döner.
     */
    public function getExamCombinableLessons(array $requestData): array
    {
        try {
            Gate::authorizeRole("department_head", false, "Sınav birleştirme listesini almak için yetkiniz yok");

            $lessonId = (int) ($requestData['lesson_id'] ?? 0);
            $search = trim($requestData['search'] ?? '');

            if (!$lessonId) {
                throw new Exception("Ders ID belirtilmemiş");
            }

            $currentLesson = clone (new Lesson())->where(['id' => $lessonId])
                ->with(['examChildLessons', 'examParentLesson'])
                ->first();
                
            if (!$currentLesson) {
                throw new Exception("Ders bulunamadı");
            }

            // Aynı akademik yıl ve dönemdeki dersleri al
            $filters = [
                'semester'      => $currentLesson->semester,
                'academic_year' => $currentLesson->academic_year,
                '!id'           => $currentLesson->id,
            ];

            $query = (new Lesson())->get()->where($filters)
                ->with(['program', 'lecturer', 'examParentLesson']);

            $lessons = $query->all();

            // Zaten bağlı olanları ve kendisini filtrele
            $existingChildIds = array_map(fn($c) => $c->id, $currentLesson->examChildLessons);
            
            $result = [];
            foreach ($lessons as $lesson) {
                // Kendisi zaten bir exam child ise atla (zaten birleştirilmiş)
                if ($lesson->exam_parent_lesson_id && $lesson->exam_parent_lesson_id !== $currentLesson->id) {
                    continue;
                }
                // Zaten bu derse bağlı olanları atla
                if (in_array($lesson->id, $existingChildIds)) {
                    continue;
                }

                $label = $lesson->getFullName(addCode: true, addProgram: true, addSize: true);

                // TomSelect arama filtresi
                if ($search !== '' && stripos($label, $search) === false) {
                    continue;
                }

                $result[] = [
                    'id'    => $lesson->id,
                    'text'  => $label,
                    'size'  => $lesson->size,
                    'program' => $lesson->program->name ?? '',
                ];
            }

            return [
                'status'  => 'success',
                'lessons' => $result,
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Excel dosyasından dersleri içe aktarır
     * @param array $files Yüklenen dosyalar
     * @param array $requestData Ek veriler
     * @return array
     */
    public function importLessons(array $files, array $requestData): array
    {
        try {
            $uploadedFile = $files['file'] ?? null;
            if (!$uploadedFile) {
                throw new Exception("Dosya yüklenmedi");
            }

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
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }
}