<?php

namespace App\Services;

use App\Models\Unit;
use App\DTOs\UnitDTO;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;
use App\DTOs\BulkActionResultDTO;
use App\Models\Department;
use App\Models\Building;
use App\Core\Database;
use App\Core\Gate;
use App\Enums\PermissionType;
use Exception;
use PDOException;

/**
 * Birim yönetimi iş mantığı servisi.
 */
class UnitService extends BaseService
{
    /**
     * Yeni birim oluşturur.
     *
     * @param UnitDTO $dto
     * @return int Oluşturulan birimin ID'si
     * @throws Exception
     */
    public function saveNew(UnitDTO $dto): int
    {
        $this->logger->debug('Yeni birim ekleniyor', ['name' => $dto->name ?? null]);

        try {
            return Database::transaction(function () use ($dto) {
                $unit = new Unit();
                $unit->fill($dto->toArray());
                $unit->create();

                $this->logger->info('Birim eklendi', ['id' => $unit->id]);
                return $unit->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu isimde bir birim zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Mevcut birimi günceller.
     *
     * @param Unit $unit
     * @return int
     * @throws Exception
     */
    public function updateUnit(Unit $unit): int
    {
        $this->logger->debug('Birim güncelleniyor', ['id' => $unit->id]);

        try {
            return Database::transaction(function () use ($unit) {
                $unit->update();
                $this->logger->info('Birim güncellendi', ['id' => $unit->id]);
                return $unit->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu isimde bir birim zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Birimi sistemden siler.
     *
     * @param Unit $unit
     * @throws Exception
     */
    public function deleteUnit(Unit $unit): void
    {
        $this->logger->debug('Birim siliniyor', ['id' => $unit->id]);

        $departments = (new Department())->get()->where(['unit_id' => $unit->id])->all();
        if (!empty($departments)) {
            $deptNames = array_map(fn($d) => $d->name, $departments);
            throw new Exception(
                "Bu birime bağlı bölümler (" . implode(', ', $deptNames) .
                ") bulunmaktadır. Birimi silmek için önce bu bölümleri silmeli veya başka bir birime taşımalısınız."
            );
        }

        $buildings = (new Building())->get()->where(['unit_id' => $unit->id])->all();
        if (!empty($buildings)) {
            $bldNames = array_map(fn($b) => $b->name, $buildings);
            throw new Exception(
                "Bu birime bağlı binalar (" . implode(', ', $bldNames) .
                ") bulunmaktadır. Birimi silmek için önce bu binaları silmeli veya başka bir birime taşımalısınız."
            );
        }

        try {
            Database::transaction(function () use ($unit) {
                $unit->delete();
            });

            $this->logger->info('Birim başarıyla silindi', ['id' => $unit->id]);
        } catch (Exception $e) {
            $this->logger->error('Birim silinirken hata oluştu', [
                'id' => $unit->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Birim silinirken bir hata oluştu: " . $e->getMessage());
        }
    }

    /**
     * Birden fazla birimi toplu siler.
     *
     * @param BulkDeleteDTO|array $dtoOrIds
     * @return BulkActionResultDTO
     */
    public function bulkDelete(BulkDeleteDTO|array $dtoOrIds): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkDeleteDTO ? $dtoOrIds : new BulkDeleteDTO(ids: array_map('intval', (array)$dtoOrIds));
        $this->logger->debug('Toplu birim silme başlatıldı', ['ids' => $dto->ids]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $unit = (new Unit())->find($id);
                if (!$unit) {
                    $failed[$id] = "Birim bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::DELETE->value, $unit)) {
                    $failed[$id] = "Silme yetkiniz yok.";
                    continue;
                }

                $this->deleteUnit($unit);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu birim silme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }

    /**
     * Birden fazla birimi toplu günceller.
     *
     * @param BulkUpdateDTO|array $dtoOrIds
     * @param array<string, mixed> $fields
     * @return BulkActionResultDTO
     */
    public function bulkUpdate(BulkUpdateDTO|array $dtoOrIds, array $fields = []): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkUpdateDTO
            ? $dtoOrIds
            : new BulkUpdateDTO(ids: array_map('intval', (array)$dtoOrIds), fields: $fields);

        $this->logger->debug('Toplu birim güncelleme başlatıldı', ['ids' => $dto->ids, 'fields' => $dto->fields]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $unit = clone (new Unit())->find($id);
                if (!$unit) {
                    $failed[$id] = "Birim bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::UPDATE->value, $unit)) {
                    $failed[$id] = "Güncelleme yetkiniz yok.";
                    continue;
                }

                foreach ($dto->fields as $fieldName => $fieldValue) {
                    $unit->{$fieldName} = $fieldValue === '' ? null : $fieldValue;
                }

                $this->updateUnit($unit);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu birim güncelleme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }
}
