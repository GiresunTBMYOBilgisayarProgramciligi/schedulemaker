<?php

namespace App\Services;

use App\Models\Classroom;
use App\DTOs\ClassroomDTO;
use App\Services\ScheduleService;
use App\Core\Database;
use Exception;
use PDOException;

/**
 * Derslik yönetimi iş mantığı servisi.
 *
 * Sorumluluklar:
 * - Derslik CRUD işlemleri (saveNew, updateClassroom)
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
        $this->logger->info('Yeni derslik ekleniyor', ['name' => $dto->name ?? null]);

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
                throw new Exception("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
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
        $this->logger->info('Derslik güncelleniyor', ['id' => $classroom->id]);

        try {
            return Database::transaction(function () use ($classroom) {
                $classroom->update();
                $this->logger->info('Derslik güncellendi', ['id' => $classroom->id]);
                return $classroom->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Dersliği sistemden siler.
     * Silme işleminden önce, dersliğin ilişkili ders programlarını temizler.
     * Bu orkestrasyon sayesinde Model, Servis katmanından bağımsız hale getirilmiştir.
     *
     * @param Classroom $classroom Silinecek derslik nesnesi
     * @throws Exception
     */
    public function deleteClassroom(Classroom $classroom): void
    {
        $this->logger->info('Derslik siliniyor', ['id' => $classroom->id]);

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
}
