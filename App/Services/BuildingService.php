<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Classroom;
use App\DTOs\BuildingDTO;
use App\Core\Database;
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
        $this->logger->info('Yeni bina ekleniyor', ['name' => $dto->name ?? null]);

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
                throw new Exception("Bu isimde bir bina zaten kayıtlı. Lütfen farklı bir isim giriniz.");
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
        $this->logger->info('Bina güncelleniyor', ['id' => $building->id]);

        try {
            return Database::transaction(function () use ($building) {
                $building->update();
                $this->logger->info('Bina güncellendi', ['id' => $building->id]);
                return $building->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu isimde bir bina zaten kayıtlı. Lütfen farklı bir isim giriniz.");
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
        $this->logger->info('Bina siliniyor', ['id' => $building->id]);

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
}
