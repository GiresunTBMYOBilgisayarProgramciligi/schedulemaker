<?php

namespace App\Services;

use App\Models\Classroom;
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
     * @param array $classroomData Derslik verileri
     * @return int Oluşturulan dersliğin ID'si
     * @throws Exception Duplicate isim veya kayıt hatası
     */
    public function saveNew(array $classroomData): int
    {
        $this->logger->info('Yeni derslik ekleniyor', ['name' => $classroomData['name'] ?? null]);

        try {
            $classroom = new Classroom();
            $classroom->fill($classroomData);
            $classroom->create();

            $this->logger->info('Derslik eklendi', ['id' => $classroom->id]);
            return $classroom->id;
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
            $classroom->update();
            $this->logger->info('Derslik güncellendi', ['id' => $classroom->id]);
            return $classroom->id;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu isimde bir derslik zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
