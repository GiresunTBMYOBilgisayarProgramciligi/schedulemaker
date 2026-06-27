<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Lesson;
use App\DTOs\ProgramDTO;
use App\Core\Database;
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
        $this->logger->info('Yeni program ekleniyor', ['name' => $dto->name ?? null]);

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
                throw new Exception("Bu isimde bir program zaten kayıtlı. Lütfen farklı bir isim giriniz.");
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
        $this->logger->info('Program güncelleniyor', ['id' => $program->id]);

        try {
            return Database::transaction(function () use ($program) {
                $program->update();
                $this->logger->info('Program güncellendi', ['id' => $program->id]);
                return $program->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu isimde bir program zaten kayıtlı. Lütfen farklı bir isim giriniz.");
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
        $this->logger->info('Program siliniyor', ['id' => $program->id]);

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
}
