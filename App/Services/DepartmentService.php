<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Program;
use App\Models\Lesson;
use App\DTOs\DepartmentDTO;
use App\Core\Database;
use Exception;
use PDOException;

class DepartmentService extends BaseService
{
    /**
     * Yeni bölüm oluşturur.
     *
     * @param DepartmentDTO $dto Bölüm verileri
     * @return int Oluşturulan bölümün ID'si
     * @throws Exception Duplicate isim veya kayıt hatası
     */
    public function saveNew(DepartmentDTO $dto): int
    {
        $this->logger->info('Yeni bölüm ekleniyor', ['name' => $dto->name ?? null]);

        try {
            return Database::transaction(function () use ($dto) {
                $department = new Department();
                $department->fill($dto->toArray());
                $department->create();

                $this->logger->info('Bölüm eklendi', ['id' => $department->id]);
                return $department->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu isimde bir bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Mevcut bölümü günceller.
     * Eğer bölüm pasif duruma çekiliyorsa, altındaki programları da pasif yapar.
     *
     * @param Department $department Güncellenmiş Department nesnesi (Veritabanındaki eski haliyle karşılaştırmak için id'si üzerinden eski kayıt okunur)
     * @return int Bölümün ID'si
     * @throws Exception
     */
    public function updateDepartment(Department $department): int
    {
        $this->logger->info('Bölüm güncelleniyor', ['id' => $department->id]);

        try {
            return Database::transaction(function () use ($department) {
                // Mevcut aktiflik durumunu kontrol et (Veritabanındaki eski hali)
                $oldDepartment = (new Department())->get()->where(['id' => $department->id])->first();
                $wasActive = $oldDepartment?->active ?? false;

                $department->update();

                // Eğer güncelleme başarılıysa ve bölüm aktiften pasife çekildiyse
                if ($wasActive && ($department->active === null || $department->active === false || $department->active === 0)) {
                    $programs = (new Program())->get()->where(['department_id' => $department->id])->all();
                    foreach ($programs as $program) {
                        $program->active = null; // Pasife al
                        $program->update();
                    }
                    $this->logger->info('Bölüm pasife alındığı için alt programları da pasife çekildi', ['department_id' => $department->id, 'affected_programs' => count($programs)]);
                }

                $this->logger->info('Bölüm güncellendi', ['id' => $department->id]);
                return $department->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu isimde bir bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Bölümü sistemden siler.
     * Silme işleminden önce, bölüme bağlı programları ve program bağımsız dersleri temizler.
     * Bu orkestrasyon sayesinde Model, İş Mantığından (Business Logic) bağımsız hale getirilmiştir.
     *
     * @param Department $department Silinecek bölüm nesnesi
     * @throws Exception
     */
    public function deleteDepartment(Department $department): void
    {
        $this->logger->info('Bölüm siliniyor', ['id' => $department->id]);

        try {
            Database::transaction(function () use ($department) {
                // 1. Önce bağlı programları sil (Bu işlem programların beforeDelete hooklarını da tetikler)
                $programs = (new Program())->get()->where(['department_id' => $department->id])->all();
                foreach ($programs as $program) {
                    $program->delete();
                }

                // 2. Program bağımsız dersleri sil (Eğer herhangi bir programa bağlı olmayan dersler varsa)
                $lessons = (new Lesson())->get()->where(['department_id' => $department->id, 'program_id' => null])->all();
                foreach ($lessons as $lesson) {
                    $lesson->delete();
                }

                // Sonra bölümü veritabanından sil
                $department->delete();
            });

            $this->logger->info('Bölüm başarıyla silindi', ['id' => $department->id]);
        } catch (Exception $e) {
            $this->logger->error('Bölüm silinirken hata oluştu', [
                'id' => $department->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Bölüm silinirken bir hata oluştu: " . $e->getMessage());
        }
    }
}
