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

}