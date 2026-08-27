<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Lesson;
use App\DTOs\ProgramDTO;
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
 * Program yönetimi iş mantığı servisi.
 */
class ProgramService extends BaseService
{
    /**
     * Yeni program oluşturur.
     *
     * @param ProgramDTO $dto Program verileri
     * @return int Oluşturulan programın ID'si
     * @throws Exception Duplicate isim veya kayıt hatası
     */
    public function saveNew(ProgramDTO $dto): int
    {
        $this->logger->debug('Yeni program ekleniyor', ['name' => $dto->name ?? null]);

        try {
            return Database::transaction(function () use ($dto) {
                $program = new Program();
                $program->fill($dto->toArray());
                $program->create();

                $this->logger->info('Program eklendi', ['id' => $program->id]);
                return $program->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu bölümde bu isimde bir program zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Mevcut programı günceller.
     *
     * @param Program $program Güncellenmiş Program nesnesi
     * @return int Programın ID'si
     * @throws Exception Duplicate isim veya güncelleme hatası
     */
    public function updateProgram(Program $program): int
    {
        $this->logger->debug('Program güncelleniyor', ['id' => $program->id]);

        try {
            return Database::transaction(function () use ($program) {
                $program->update();
                $this->logger->info('Program güncellendi', ['id' => $program->id]);
                return $program->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu bölümde bu isimde bir program zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Programı sistemden siler.
     *
     * @param Program $program Silinecek program nesnesi
     * @throws Exception
     */
    public function deleteProgram(Program $program): void
    {
        $this->logger->debug('Program siliniyor', ['id' => $program->id]);

        $lessons = (new Lesson())->get()->where(['program_id' => $program->id])->all();
        if (!empty($lessons)) {
            $lessonNames = array_map(fn($l) => $l->name, $lessons);
            throw new Exception(
                "Bu programa bağlı dersler (" . implode(', ', $lessonNames) .
                ") bulunmaktadır. Programı silmek için önce bu dersleri silmeli veya başka bir programa taşımalısınız."
            );
        }

        try {
            Database::transaction(function () use ($program) {
                (new ScheduleService())->wipeResourceSchedules('program', $program->id);
                $program->delete();
            });

            $this->logger->info('Program başarıyla silindi', ['id' => $program->id]);
        } catch (Exception $e) {
            $this->logger->error('Program silinirken hata oluştu', [
                'id' => $program->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Program silinirken bir hata oluştu: " . $e->getMessage());
        }
    }

    /**
     * Birden fazla programı toplu siler.
     *
     * @param BulkDeleteDTO|array $dtoOrIds
     * @return BulkActionResultDTO
     */
    public function bulkDelete(BulkDeleteDTO|array $dtoOrIds): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkDeleteDTO ? $dtoOrIds : new BulkDeleteDTO(ids: array_map('intval', (array)$dtoOrIds));
        $this->logger->debug('Toplu program silme başlatıldı', ['ids' => $dto->ids]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $program = (new Program())->find($id);
                if (!$program) {
                    $failed[$id] = "Program bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::DELETE->value, $program)) {
                    $failed[$id] = "Silme yetkiniz yok.";
                    continue;
                }

                $this->deleteProgram($program);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu program silme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }

    /**
     * Birden fazla programı toplu günceller.
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

        $this->logger->debug('Toplu program güncelleme başlatıldı', ['ids' => $dto->ids, 'fields' => $dto->fields]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $program = clone (new Program())->find($id);
                if (!$program) {
                    $failed[$id] = "Program bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::UPDATE->value, $program)) {
                    $failed[$id] = "Güncelleme yetkiniz yok.";
                    continue;
                }

                foreach ($dto->fields as $fieldName => $fieldValue) {
                    $program->{$fieldName} = $fieldValue === '' ? null : $fieldValue;
                }

                $this->updateProgram($program);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu program güncelleme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }
}
