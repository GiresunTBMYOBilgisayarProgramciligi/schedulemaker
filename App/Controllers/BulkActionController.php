<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Validators\BulkActionValidator;
use App\Services\ProgramService;
use App\Services\LessonService;
use App\Services\UserService;
use App\Services\ClassroomService;
use App\Services\DepartmentService;
use App\Services\UnitService;
use App\Services\BuildingService;
use Exception;

/**
 * Toplu işlem (bulk action) isteklerini yöneten controller.
 *
 * Her entity için bulkDelete ve bulkUpdate metotları:
 * 1. BulkActionValidator ile veriyi doğrular
 * 2. DTO'ya çevirir
 * 3. İlgili Service'i çağırır
 * 4. Sonuç mesajını döner
 */
class BulkActionController extends Controller
{
    private BulkActionValidator $validator;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new BulkActionValidator();
    }

    /**
     * Sonuç dizisinden standart AJAX yanıtı oluşturur.
     *
     * @param array{success: int[], failed: array<int, string>} $result
     * @param string $entityLabel Entity'nin Türkçe adı
     * @param string $actionLabel İşlemin Türkçe adı (silindi, güncellendi)
     * @return array
     */
    private function buildResponse(array $result, string $entityLabel, string $actionLabel): array
    {
        $successCount = count($result['success']);
        $failedCount = count($result['failed']);

        $messages = [];
        $messages[] = "{$successCount} adet {$entityLabel} başarıyla {$actionLabel}.";

        if ($failedCount > 0) {
            $messages[] = "{$failedCount} adet {$entityLabel} işlenemedi.";
        }

        return [
            'status' => $failedCount === 0 && $successCount > 0 ? 'success' : ($successCount === 0 ? 'error' : 'warning'),
            'msg' => implode(' ', $messages),
            'details' => [
                'success' => $result['success'],
                'failed' => $result['failed'],
            ],
        ];
    }

    // ──────────────────────────────────────────
    // Program
    // ──────────────────────────────────────────

    /** @throws Exception */
    public function bulkDeletePrograms(array $requestData): array
    {
        $dto = $this->validator->getDeleteDTO($requestData);
        $result = (new ProgramService())->bulkDelete($dto->ids);
        return $this->buildResponse($result, 'program', 'silindi');
    }

    /** @throws Exception */
    public function bulkUpdatePrograms(array $requestData): array
    {
        $dto = $this->validator->getUpdateDTO($requestData, 'program');
        $result = (new ProgramService())->bulkUpdate($dto->ids, $dto->fields);
        return $this->buildResponse($result, 'program', 'güncellendi');
    }

    // ──────────────────────────────────────────
    // Lesson
    // ──────────────────────────────────────────

    /** @throws Exception */
    public function bulkDeleteLessons(array $requestData): array
    {
        $dto = $this->validator->getDeleteDTO($requestData);
        $result = (new LessonService())->bulkDelete($dto->ids);
        return $this->buildResponse($result, 'ders', 'silindi');
    }

    /** @throws Exception */
    public function bulkUpdateLessons(array $requestData): array
    {
        $dto = $this->validator->getUpdateDTO($requestData, 'lesson');
        $result = (new LessonService())->bulkUpdate($dto->ids, $dto->fields);
        return $this->buildResponse($result, 'ders', 'güncellendi');
    }

    // ──────────────────────────────────────────
    // User
    // ──────────────────────────────────────────

    /** @throws Exception */
    public function bulkDeleteUsers(array $requestData): array
    {
        $dto = $this->validator->getDeleteDTO($requestData);
        $result = (new UserService())->bulkDelete($dto->ids);
        return $this->buildResponse($result, 'kullanıcı', 'silindi');
    }

    /** @throws Exception */
    public function bulkUpdateUsers(array $requestData): array
    {
        $dto = $this->validator->getUpdateDTO($requestData, 'user');
        $result = (new UserService())->bulkUpdate($dto->ids, $dto->fields);
        return $this->buildResponse($result, 'kullanıcı', 'güncellendi');
    }

    // ──────────────────────────────────────────
    // Classroom
    // ──────────────────────────────────────────

    /** @throws Exception */
    public function bulkDeleteClassrooms(array $requestData): array
    {
        $dto = $this->validator->getDeleteDTO($requestData);
        $result = (new ClassroomService())->bulkDelete($dto->ids);
        return $this->buildResponse($result, 'derslik', 'silindi');
    }

    /** @throws Exception */
    public function bulkUpdateClassrooms(array $requestData): array
    {
        $dto = $this->validator->getUpdateDTO($requestData, 'classroom');
        $result = (new ClassroomService())->bulkUpdate($dto->ids, $dto->fields);
        return $this->buildResponse($result, 'derslik', 'güncellendi');
    }

    // ──────────────────────────────────────────
    // Department
    // ──────────────────────────────────────────

    /** @throws Exception */
    public function bulkDeleteDepartments(array $requestData): array
    {
        $dto = $this->validator->getDeleteDTO($requestData);
        $result = (new DepartmentService())->bulkDelete($dto->ids);
        return $this->buildResponse($result, 'bölüm', 'silindi');
    }

    /** @throws Exception */
    public function bulkUpdateDepartments(array $requestData): array
    {
        $dto = $this->validator->getUpdateDTO($requestData, 'department');
        $result = (new DepartmentService())->bulkUpdate($dto->ids, $dto->fields);
        return $this->buildResponse($result, 'bölüm', 'güncellendi');
    }

    // ──────────────────────────────────────────
    // Unit
    // ──────────────────────────────────────────

    /** @throws Exception */
    public function bulkDeleteUnits(array $requestData): array
    {
        $dto = $this->validator->getDeleteDTO($requestData);
        $result = (new UnitService())->bulkDelete($dto->ids);
        return $this->buildResponse($result, 'birim', 'silindi');
    }

    /** @throws Exception */
    public function bulkUpdateUnits(array $requestData): array
    {
        $dto = $this->validator->getUpdateDTO($requestData, 'unit');
        $result = (new UnitService())->bulkUpdate($dto->ids, $dto->fields);
        return $this->buildResponse($result, 'birim', 'güncellendi');
    }

    // ──────────────────────────────────────────
    // Building
    // ──────────────────────────────────────────

    /** @throws Exception */
    public function bulkDeleteBuildings(array $requestData): array
    {
        $dto = $this->validator->getDeleteDTO($requestData);
        $result = (new BuildingService())->bulkDelete($dto->ids);
        return $this->buildResponse($result, 'bina', 'silindi');
    }

    /** @throws Exception */
    public function bulkUpdateBuildings(array $requestData): array
    {
        $dto = $this->validator->getUpdateDTO($requestData, 'building');
        $result = (new BuildingService())->bulkUpdate($dto->ids, $dto->fields);
        return $this->buildResponse($result, 'bina', 'güncellendi');
    }
}
