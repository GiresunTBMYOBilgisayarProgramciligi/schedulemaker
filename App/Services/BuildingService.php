<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Classroom;
use App\DTOs\BuildingDTO;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;
use App\DTOs\BulkActionResultDTO;
use App\Core\Database;
use App\Core\Gate;
use App\Enums\PermissionType;
use Exception;
use PDOException;

class BuildingService extends BaseService
{
    /**
     * Yeni bina oluşturur.
     *
     * @param BuildingDTO $dto
     * @return int Oluşturulan binanın ID'si
     * @throws Exception
     */
    public function saveNew(BuildingDTO $dto): int
    {
        $this->logger->debug('Yeni bina ekleniyor', ['name' => $dto->name ?? null]);

        try {
            return Database::transaction(function () use ($dto) {
                $building = new Building();
                $building->fill($dto->toArray());
                $building->create();

                $this->logger->info('Bina eklendi', ['id' => $building->id]);
                return $building->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu birimde bu isimde bir bina zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Mevcut binayı günceller.
     *
     * @param Building $building
     * @return int
     * @throws Exception
     */
    public function updateBuilding(Building $building): int
    {
        $this->logger->debug('Bina güncelleniyor', ['id' => $building->id]);

        try {
            return Database::transaction(function () use ($building) {
                $building->update();
                $this->logger->info('Bina güncellendi', ['id' => $building->id]);
                return $building->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu birimde bu isimde bir bina zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Binayı sistemden siler.
     * Bağlı dersliklerin building_id'si NULL yapılır.
     *
     * @param Building $building
     * @throws Exception
     */
    public function deleteBuilding(Building $building): void
    {
        $this->logger->debug('Bina siliniyor', ['id' => $building->id]);

        try {
            Database::transaction(function () use ($building) {
                // Bağlı dersliklerin building_id'sini temizle
                $classrooms = (new Classroom())->get()->where(['building_id' => $building->id])->all();
                foreach ($classrooms as $classroom) {
                    $classroom->building_id = null;
                    $classroom->update();
                }

                $building->delete();
            });

            $this->logger->info('Bina başarıyla silindi', ['id' => $building->id]);
        } catch (Exception $e) {
            $this->logger->error('Bina silinirken hata oluştu', [
                'id'    => $building->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Bina silinirken bir hata oluştu: " . $e->getMessage());
        }
    }

    /**
     * Birden fazla binayı toplu siler.
     *
     * @param BulkDeleteDTO|array $dtoOrIds
     * @return BulkActionResultDTO
     */
    public function bulkDelete(BulkDeleteDTO|array $dtoOrIds): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkDeleteDTO ? $dtoOrIds : new BulkDeleteDTO(ids: array_map('intval', $dtoOrIds));
        $this->logger->debug('Toplu bina silme başlatıldı', ['ids' => $dto->ids]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $building = (new Building())->find($id);
                if (!$building) {
                    $failed[$id] = "Bina bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::DELETE->value, $building)) {
                    $failed[$id] = "Silme yetkiniz yok.";
                    continue;
                }

                $this->deleteBuilding($building);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu bina silme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }

    /**
     * Birden fazla binayı toplu günceller.
     *
     * @param BulkUpdateDTO|array $dtoOrIds
     * @param array<string, mixed> $fields
     * @return BulkActionResultDTO
     */
    public function bulkUpdate(BulkUpdateDTO|array $dtoOrIds, array $fields = []): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkUpdateDTO 
            ? $dtoOrIds 
            : new BulkUpdateDTO(ids: array_map('intval', $dtoOrIds), fields: $fields);

        $this->logger->debug('Toplu bina güncelleme başlatıldı', ['ids' => $dto->ids, 'fields' => $dto->fields]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $building = clone (new Building())->find($id);
                if (!$building) {
                    $failed[$id] = "Bina bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::UPDATE->value, $building)) {
                    $failed[$id] = "Güncelleme yetkiniz yok.";
                    continue;
                }

                foreach ($dto->fields as $fieldName => $fieldValue) {
                    $building->{$fieldName} = $fieldValue === '' ? null : $fieldValue;
                }

                $this->updateBuilding($building);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu bina güncelleme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }
}
