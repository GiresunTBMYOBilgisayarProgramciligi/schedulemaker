<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Classroom;
use App\Services\ClassroomService;
use App\Validators\ClassroomValidator;
use App\DTOs\ClassroomDTO;
use App\Core\Gate;
use App\Enums\ClassroomType;
use Exception;

/**
 * Controller sınıfından türetilmiştir. Derslikler ile ilgili işlemleri yönetir.
 */
class ClassroomController extends Controller
{
    protected string $table_name = "classrooms";
    protected string $modelName = "App\Models\Classroom";



    /**
     * Yeni derslik oluşturur (POST /ajax/classroom/add rotası için)
     */
    public function store(array $requestData): array
    {
        try {
            Gate::authorize("create", Classroom::class, "Yeni derslik oluşturma yetkiniz yok");

            $validator = new ClassroomValidator();
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            $dto = ClassroomDTO::fromArray($requestData);
            (new ClassroomService())->saveNew($dto);

            return [
                "status" => "success",
                "msg" => "Derslik başarıyla oluşturuldu."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Mevcut dersliği günceller (POST /ajax/classroom/update rotası için)
     */
    public function update(array $requestData): array
    {
        try {
            $classroom = clone (new Classroom())->find($requestData['id']);
            if (!$classroom) {
                throw new Exception("Güncellenecek derslik bulunamadı.");
            }

            Gate::authorize("update", $classroom, "Derslik güncelleme yetkiniz yok");

            $validator = new ClassroomValidator();
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            $dto = ClassroomDTO::fromArray($requestData);
            
            // DTO'dan Model'e aktar
            $classroom->fill(array_merge(['id' => $requestData['id']], $dto->toArray()));

            (new ClassroomService())->updateClassroom($classroom);

            return [
                "status" => "success",
                "msg" => "Derslik başarıyla güncellendi."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Dersliği siler (POST /ajax/classroom/delete rotası için)
     */
    public function destroy(array $requestData): array
    {
        try {
            if (empty($requestData['id'])) {
                throw new Exception("Silinecek derslik ID'si belirtilmedi.");
            }

            $classroom = clone (new Classroom())->find($requestData['id']);
            if (!$classroom) {
                throw new Exception("Silinecek derslik bulunamadı.");
            }

            Gate::authorize("delete", $classroom, "Derslik silme yetkiniz yok");

            (new ClassroomService())->deleteClassroom($classroom);

            return [
                "status" => "success",
                "msg" => "Derslik başarıyla silindi."
            ];

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }


}