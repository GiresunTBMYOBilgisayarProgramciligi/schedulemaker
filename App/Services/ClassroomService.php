<?php

namespace App\Services;

use App\Models\Classroom;
use App\DTOs\ClassroomDTO;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;
use App\DTOs\BulkActionResultDTO;
use App\Services\Schedule\ScheduleService;
use App\Core\Database;
use App\Core\Gate;
use App\Enums\PermissionType;
use Exception;
use PDOException;

/**
 * Derslik yönetimi iş mantığı servisi.
 */
class ClassroomService extends BaseService
{
    /**
     * Yeni derslik oluşturur.
     *
     * @param ClassroomDTO $dto Derslik verileri
     * @return int Oluşturulan dersliğin ID'si
     * @throws Exception Duplicate isim veya kayıt hatası
     */
    public function saveNew(ClassroomDTO $dto): int
    {
        $this->logger->debug('Yeni derslik ekleniyor', ['name' => $dto->name ?? null]);

        try {
            return Database::transaction(function () use ($dto) {
                $classroom = new Classroom();
                $classroom->fill($dto->toArray());
                $classroom->create();

                $this->logger->info('Derslik eklendi', ['id' => $classroom->id]);
                return $classroom->id;
            });
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu binada bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Mevcut dersliği günceller.
     *
     * @param Classroom $classroom Güncellenmiş Classroom nesnesi
     * @return int Dersliğin ID'si
     * @throws Exception Duplicate isim veya güncelleme hatası
     */
    public function updateClassroom(Classroom $classroom): int
    {
        $this->logger->debug('Derslik güncelleniyor', ['id' => $classroom->id]);

        try {
            return Database::transaction(function () use ($classroom) {
                $classroom->update();
                $this->logger->info('Derslik güncellendi', ['id' => $classroom->id]);
                return $classroom->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu binada bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Dersliği sistemden siler.
     *
     * @param Classroom $classroom Silinecek derslik nesnesi
     * @throws Exception
     */
    public function deleteClassroom(Classroom $classroom): void
    {
        $this->logger->debug('Derslik siliniyor', ['id' => $classroom->id]);

        try {
            Database::transaction(function () use ($classroom) {
                // Önce dersliğe ait ders programı kayıtlarını temizle
                (new ScheduleService())->wipeResourceSchedules('classroom', $classroom->id);

                // Sonra dersliği veritabanından sil
                $classroom->delete();
            });

            $this->logger->info('Derslik başarıyla silindi', ['id' => $classroom->id]);
        } catch (Exception $e) {
            $this->logger->error('Derslik silinirken hata oluştu', [
                'id' => $classroom->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Derslik silinirken bir hata oluştu: " . $e->getMessage());
        }
    }

    /**
     * Birden fazla dersliği toplu siler.
     *
     * @param BulkDeleteDTO|array $dtoOrIds
     * @return BulkActionResultDTO
     */
    public function bulkDelete(BulkDeleteDTO|array $dtoOrIds): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkDeleteDTO ? $dtoOrIds : new BulkDeleteDTO(ids: array_map('intval', (array)$dtoOrIds));
        $this->logger->debug('Toplu derslik silme başlatıldı', ['ids' => $dto->ids]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $classroom = (new Classroom())->find($id);
                if (!$classroom) {
                    $failed[$id] = "Derslik bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::DELETE->value, $classroom)) {
                    $failed[$id] = "Silme yetkiniz yok.";
                    continue;
                }

                $this->deleteClassroom($classroom);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu derslik silme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }

    /**
     * Birden fazla dersliği toplu günceller.
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

        $this->logger->debug('Toplu derslik güncelleme başlatıldı', ['ids' => $dto->ids, 'fields' => $dto->fields]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $classroom = clone (new Classroom())->find($id);
                if (!$classroom) {
                    $failed[$id] = "Derslik bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::UPDATE->value, $classroom)) {
                    $failed[$id] = "Güncelleme yetkiniz yok.";
                    continue;
                }

                foreach ($dto->fields as $fieldName => $fieldValue) {
                    $classroom->{$fieldName} = $fieldValue === '' ? null : $fieldValue;
                }

                $this->updateClassroom($classroom);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu derslik güncelleme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }
}
