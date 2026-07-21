<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\Department;
use App\DTOs\UnitDTO;
use App\Core\Database;
use Exception;
use PDOException;

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
        $this->logger->info('Yeni birim ekleniyor', ['name' => $dto->name ?? null]);

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
        $this->logger->info('Birim güncelleniyor', ['id' => $unit->id]);

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
     * Silmeden önce bağlı bölümlerin unit_id'si NULL yapılır.
     *
     * @param Unit $unit
     * @throws Exception
     */
    public function deleteUnit(Unit $unit): void
    {
        $this->logger->info('Birim siliniyor', ['id' => $unit->id]);

        try {
            Database::transaction(function () use ($unit) {
                // Bağlı bölümlerin unit_id'sini temizle
                $departments = (new Department())->get()->where(['unit_id' => $unit->id])->all();
                foreach ($departments as $department) {
                    $department->unit_id = null;
                    $department->active = false;
                    $department->update();
                }

                $unit->delete();
            });

            $this->logger->info('Birim başarıyla silindi', ['id' => $unit->id]);
        } catch (PDOException $e) {
            $this->logger->error('Birim silinirken hata oluştu', [
                'id'    => $unit->id,
                'error' => $e->getMessage()
            ]);
            if ($e->getCode() == '23000') {
                throw new Exception("Bu birimi silmek için öncelikle altındaki tüm binaları silmeli ya da başka bir birime taşımalısınız.");
            }
            throw new Exception("Birim silinirken bir hata oluştu.");
        } catch (Exception $e) {
            $this->logger->error('Birim silinirken hata oluştu', [
                'id'    => $unit->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Birim silinirken bir hata oluştu.");
        }
    }
}
