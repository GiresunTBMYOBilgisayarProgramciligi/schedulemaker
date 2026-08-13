<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Program;
use App\Models\Lesson;
use App\DTOs\DepartmentDTO;
use App\Core\Database;
use App\Core\EventDispatcher;
use App\Events\ChairpersonChangedEvent;
use App\Core\Gate;
use App\Enums\PermissionType;
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
        $this->logger->debug('Yeni bölüm ekleniyor', ['name' => $dto->name ?? null]);

        try {
            $departmentId = Database::transaction(function () use ($dto) {
                $department = new Department();
                $department->fill($dto->toArray());
                $department->create();

                $this->logger->info('Bölüm eklendi', ['id' => $department->id]);
                return $department->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw new Exception("Bu birimde bu isimde bir bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
                } else {
                    $this->logger->error('Veritabanı bütünlük hatası: ' . $e->getMessage());
                    throw new Exception("Geçersiz veya eksik bir bilgi girdiniz. Lütfen seçimlerinizi kontrol edin.");
                }
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }

        // Bölüm başkanı rol senkronizasyonu (sadece bölüm başarıyla oluşturulduysa)
        if ($dto->chairperson_id !== null) {
            EventDispatcher::getInstance()->dispatch(
                new ChairpersonChangedEvent(null, $dto->chairperson_id)
            );
        }

        return $departmentId;
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
        $this->logger->debug('Bölüm güncelleniyor', ['id' => $department->id]);

        // Başkan değişikliğini tespit etmek için eski kaydı transaction öncesinde oku
        $oldDepartment = (new Department())->get()->where(['id' => $department->id])->first();
        $oldChairpersonId = $oldDepartment?->chairperson_id;

        try {
            $departmentId = Database::transaction(function () use ($department, $oldDepartment) {
                $wasActive = $oldDepartment?->active ?? false;

                $department->update();

                // Eğer güncelleme başarılıysa ve bölüm aktiften pasife çekildiyse
                if ($wasActive && ($department->active === null || $department->active === false || $department->active === 0)) {
                    $programs = (new Program())->get()->where(['department_id' => $department->id])->all();
                    foreach ($programs as $program) {
                        $program->active = 0; // Pasife al
                        $program->update();
                    }
                    $this->logger->info('Bölüm pasife alındığı için alt programları da pasife çekildi', ['department_id' => $department->id, 'affected_programs' => count($programs)]);
                }

                $this->logger->info('Bölüm güncellendi', ['id' => $department->id]);
                return $department->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw new Exception("Bu birimde bu isimde bir bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
                } else {
                    $this->logger->error('Veritabanı bütünlük hatası: ' . $e->getMessage());
                    throw new Exception("Geçersiz veya eksik bir bilgi girdiniz. Lütfen seçimlerinizi kontrol edin.");
                }
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }

        // Başkan değiştiyse rol senkronizasyonu tetikle (sadece güncelleme başarılıysa)
        if ($oldChairpersonId !== $department->chairperson_id) {
            EventDispatcher::getInstance()->dispatch(
                new ChairpersonChangedEvent($oldChairpersonId, $department->chairperson_id)
            );
        }

        return $departmentId;
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
        $this->logger->debug('Bölüm siliniyor', ['id' => $department->id]);

        // Silme öncesi başkan ID'sini sakla (silme sonrası erişilemez olacak)
        $oldChairpersonId = $department->chairperson_id;

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

        // Eski başkanın rolünü düşür (sadece silme başarılıysa)
        if ($oldChairpersonId !== null) {
            EventDispatcher::getInstance()->dispatch(
                new ChairpersonChangedEvent($oldChairpersonId, null)
            );
        }
    }

    /**
     * Birden fazla bölümü toplu siler.
     *
     * @param int[] $ids
     * @return array{success: int[], failed: array<int, string>}
     */
    public function bulkDelete(array $ids): array
    {
        $this->logger->debug('Toplu bölüm silme başlatıldı', ['ids' => $ids]);

        $success = [];
        $failed = [];

        foreach ($ids as $id) {
            try {
                $department = (new Department())->find($id);
                if (!$department) {
                    $failed[$id] = "Bölüm bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::DELETE->value, $department)) {
                    $failed[$id] = "Silme yetkiniz yok.";
                    continue;
                }

                $this->deleteDepartment($department);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu bölüm silme tamamlandı', [
            'success_count' => count($success),
            'failed_count' => count($failed)
        ]);

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Birden fazla bölümü toplu günceller.
     *
     * @param int[] $ids
     * @param array<string, mixed> $fields
     * @return array{success: int[], failed: array<int, string>}
     */
    public function bulkUpdate(array $ids, array $fields): array
    {
        $this->logger->debug('Toplu bölüm güncelleme başlatıldı', ['ids' => $ids, 'fields' => $fields]);

        $success = [];
        $failed = [];

        foreach ($ids as $id) {
            try {
                $department = clone (new Department())->find($id);
                if (!$department) {
                    $failed[$id] = "Bölüm bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::UPDATE->value, $department)) {
                    $failed[$id] = "Güncelleme yetkiniz yok.";
                    continue;
                }

                foreach ($fields as $fieldName => $fieldValue) {
                    if ($fieldName === 'active') {
                        $department->active = filter_var($fieldValue, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $department->{$fieldName} = $fieldValue === '' ? null : $fieldValue;
                    }
                }

                $this->updateDepartment($department);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu bölüm güncelleme tamamlandı', [
            'success_count' => count($success),
            'failed_count' => count($failed)
        ]);

        return ['success' => $success, 'failed' => $failed];
    }
}
