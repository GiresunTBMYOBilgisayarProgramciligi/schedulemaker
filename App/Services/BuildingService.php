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
use App\Models\User;
use App\Repositories\BuildingRepository;
use PDOException;

class BuildingService extends BaseService
{
    private BuildingRepository $buildingRepository;

    public function __construct(?BuildingRepository $buildingRepository = null)
    {
        parent::__construct();
        $this->buildingRepository = $buildingRepository ?? new BuildingRepository();
    }

    /**
     * Binaları Birim Adı -> Bina Adı hiyerarşisine göre sıralar.
     *
     * @param Building[] $buildings
     * @param bool $groupByUnit
     * @return Building[]
     */
    public function sortBuildingsHierarchically(array $buildings, bool $groupByUnit = true): array
    {
        usort($buildings, function (Building $a, Building $b) use ($groupByUnit) {
            if ($groupByUnit) {
                $unitA = $a->unit?->name ?? 'Diğer Birim';
                $unitB = $b->unit?->name ?? 'Diğer Birim';
                $unitCmp = strcmp($unitA, $unitB);
                if ($unitCmp !== 0) {
                    return $unitCmp;
                }
            }

            return strcmp(
                mb_strtolower($a->name ?? '', 'UTF-8'),
                mb_strtolower($b->name ?? '', 'UTF-8')
            );
        });

        return $buildings;
    }

    /**
     * Yetkili binaları çeker, sıralar ve birim durumunu hesaplar.
     *
     * @param User|null $user
     * @param array $conditions
     * @return array{buildings: Building[], has_multiple_units: bool}
     * @throws Exception
     */
    public function getAuthorizedBuildingsData(?User $user = null, array $conditions = []): array
    {
        $buildings = $this->buildingRepository->getAuthorizedBuildingsWithHierarchy($user, $conditions);

        $unitIds = array_unique(array_filter(array_map(
            fn($b) => $b->unit_id,
            $buildings
        )));
        $hasMultipleUnits = count($unitIds) > 1;

        $buildings = $this->sortBuildingsHierarchically($buildings, count($unitIds) > 0);

        return [
            'buildings' => $buildings,
            'has_multiple_units' => $hasMultipleUnits,
        ];
    }

    /**
     * Bina listesini standart hiyerarşik HTML <select> seçenekleri (<optgroup>, <option>) olarak render eder.
     *
     * @param Building[] $buildings
     * @param int|null $selectedBuildingId
     * @param bool|null $groupByUnit
     * @param string $emptyOptionLabel
     * @return string
     */
    public function renderBuildingSelectOptions(
        array $buildings,
        ?int $selectedBuildingId = null,
        ?bool $groupByUnit = null,
        string $emptyOptionLabel = '-- Seçiniz --'
    ): string {
        $html = '';
        if ($emptyOptionLabel !== '') {
            $html .= '<option value="">' . htmlspecialchars($emptyOptionLabel) . '</option>';
        }

        if (empty($buildings)) {
            return $html;
        }

        if ($groupByUnit === null) {
            $unitIds = array_unique(array_filter(array_map(
                fn($b) => $b->unit_id,
                $buildings
            )));
            $groupByUnit = count($unitIds) > 0;
        }

        $currentUnit = null;

        foreach ($buildings as $bld) {
            if ($groupByUnit) {
                $unitName = $bld->unit?->name ?? 'Diğer Birim';
                if ($currentUnit !== $unitName) {
                    if ($currentUnit !== null) {
                        $html .= '</optgroup>';
                    }
                    $currentUnit = $unitName;
                    $html .= '<optgroup label="' . htmlspecialchars($currentUnit) . '">';
                }
            }

            $selected = ($selectedBuildingId !== null && (int)$selectedBuildingId === (int)$bld->id) ? ' selected' : '';
            $unitId = (int)($bld->unit_id ?? 0);
            $unitNameAttr = ' data-unit-name="' . htmlspecialchars($bld->unit?->name ?? 'Diğer Birim') . '"';

            $html .= '<option value="' . $bld->id . '" data-unit-id="' . $unitId . '"' . $unitNameAttr . $selected . '>'
                . htmlspecialchars($bld->name ?? '')
                . '</option>';
        }

        if ($groupByUnit && $currentUnit !== null) {
            $html .= '</optgroup>';
        }

        return $html;
    }

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
