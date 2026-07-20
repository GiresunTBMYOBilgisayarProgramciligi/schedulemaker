<?php

namespace App\Controllers;

use App\Enums\PermissionType;

use App\Core\Controller;
use App\Models\Classroom;
use App\Services\ClassroomService;
use App\Validators\ClassroomValidator;
use App\DTOs\ClassroomDTO;
use App\Core\Gate;
use App\Enums\ClassroomType;
use Exception;
use App\Exceptions\ValidationException;

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
            $dto = (new ClassroomValidator())->getDTO($requestData);
            Gate::authorize(PermissionType::CREATE->value, Classroom::class, "Yeni derslik oluşturma yetkiniz yok", $dto);

            (new ClassroomService())->saveNew($dto);

            return [
                "status" => "success",
                "msg" => "Derslik başarıyla oluşturuldu."
            ];
    }

    /**
     * Mevcut dersliği günceller (POST /ajax/classroom/update rotası için)
     */
    public function update(array $requestData): array
    {            $classroom = clone (new Classroom())->find($requestData['id']);
            if (!$classroom) {
                throw new Exception("Güncellenecek derslik bulunamadı.");
            }

            Gate::authorize(PermissionType::UPDATE->value, $classroom, "Derslik güncelleme yetkiniz yok");

            $dto = (new ClassroomValidator())->getDTO($requestData);
            
            // DTO'dan Model'e aktar
            $classroom->fill(array_merge(['id' => $requestData['id']], $dto->toArray()));

            (new ClassroomService())->updateClassroom($classroom);

            return [
                "status" => "success",
                "msg" => "Derslik başarıyla güncellendi."
            ];
    }

    /**
     * Dersliği siler (POST /ajax/classroom/delete rotası için)
     */
    public function destroy(array $requestData): array
    {            if (empty($requestData['id'])) {
                throw new Exception("Silinecek derslik ID'si belirtilmedi.");
            }

            $classroom = clone (new Classroom())->find($requestData['id']);
            if (!$classroom) {
                throw new Exception("Silinecek derslik bulunamadı.");
            }

            Gate::authorize(PermissionType::DELETE->value, $classroom, "Derslik silme yetkiniz yok");

            (new ClassroomService())->deleteClassroom($classroom);

            return [
                "status" => "success",
                "msg" => "Derslik başarıyla silindi."
            ];
    }


}