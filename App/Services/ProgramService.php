<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Lesson;
use App\DTOs\ProgramDTO;
use App\Services\Schedule\ScheduleService;
use App\Core\Database;
use App\Core\Gate;
use App\Enums\PermissionType;
use Exception;
use PDOException;

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
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw new Exception("Bu bölümde bu isimde bir program zaten kayıtlı. Lütfen farklı bir isim giriniz.");
                } else {
                    $this->logger->error('Veritabanı bütünlük hatası: ' . $e->getMessage());
                    throw new Exception("Geçersiz veya eksik bir bilgi girdiniz. Lütfen seçimlerinizi kontrol edin.");
                }
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Mevcut programı günceller.
     *
     * @param Program $program Güncellenmiş Program nesnesi
     * @return int Programın ID'si
     * @throws Exception
     */
    public function updateProgram(Program $program): int
    {
        $this->logger->debug('Program güncelleniyor', ['program' => $program]);

        try {
            return Database::transaction(function () use ($program) {
                $program->update();
                $this->logger->info('Program güncellendi', ['program' => $program]);
                return $program->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw new Exception("Bu bölümde bu isimde bir program zaten kayıtlı. Lütfen farklı bir isim giriniz.");
                } else {
                    $this->logger->error('Veritabanı bütünlük hatası: ' . $e->getMessage());
                    throw new Exception("Geçersiz veya eksik bir bilgi girdiniz. Lütfen seçimlerinizi kontrol edin.");
                }
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Programı sistemden siler.
     * Silme işleminden önce, programa bağlı dersleri ve programın kendi takvimini temizler.
     *
     * @param Program $program Silinecek program nesnesi
     * @throws Exception
     */
    public function deleteProgram(Program $program): void
    {
        $this->logger->debug('Program siliniyor', ['id' => $program->id]);

        try {
            Database::transaction(function () use ($program) {
                // 1. Polimorfik kardeş kayıtları (sibling items) ve bu programın kendi takvimini temizle
                (new ScheduleService())->wipeResourceSchedules('program', $program->id);

                // 2. Bağlı tüm dersleri PHP üzerinden sil (Böylece derslerin beforeDelete hookları - ileride LessonService'e geçince - tetiklenir)
                $lessons = (new Lesson())->get()->where(['program_id' => $program->id])->all();
                foreach ($lessons as $lesson) {
                    $lesson->delete(); // FIXME: İleride LessonService->deleteLesson() olacak.
                }

                // Sonra programı veritabanından sil
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
     * Her kayıt için ayrı ayrı yetki kontrolü yapılır.
     *
     * @param int[] $ids Silinecek program ID'leri
     * @return array{success: int[], failed: array<int, string>}
     */
    public function bulkDelete(array $ids): array
    {
        $this->logger->debug('Toplu program silme başlatıldı', ['ids' => $ids]);

        $success = [];
        $failed = [];

        foreach ($ids as $id) {
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
            'failed_count' => count($failed)
        ]);

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Birden fazla programı toplu günceller.
     * Her kayıt için ayrı ayrı yetki kontrolü yapılır.
     *
     * @param int[] $ids Güncellenecek program ID'leri
     * @param array<string, mixed> $fields Güncellenecek alanlar
     * @return array{success: int[], failed: array<int, string>}
     */
    public function bulkUpdate(array $ids, array $fields): array
    {
        $this->logger->debug('Toplu program güncelleme başlatıldı', ['ids' => $ids, 'fields' => $fields]);

        $success = [];
        $failed = [];

        foreach ($ids as $id) {
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

                // Gelen alanları modele uygula
                foreach ($fields as $fieldName => $fieldValue) {
                    if ($fieldName === 'active') {
                        $program->active = filter_var($fieldValue, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $program->{$fieldName} = $fieldValue === '' ? null : $fieldValue;
                    }
                }

                $this->updateProgram($program);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu program güncelleme tamamlandı', [
            'success_count' => count($success),
            'failed_count' => count($failed)
        ]);

        return ['success' => $success, 'failed' => $failed];
    }
}
