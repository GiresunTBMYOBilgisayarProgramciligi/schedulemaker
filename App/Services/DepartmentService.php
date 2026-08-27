<?php

namespace App\Services;

use App\Models\Department;
use App\DTOs\DepartmentDTO;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;
use App\DTOs\BulkActionResultDTO;
use App\Models\Program;
use App\Models\User;
use App\Core\Database;
use App\Core\Gate;
use App\Enums\PermissionType;
use Exception;
use PDOException;

/**
 * Bölüm yönetimi iş mantığı servisi.
 */
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
            return Database::transaction(function () use ($dto) {
                $department = new Department();
                $department->fill($dto->toArray());
                $department->create();

                if (!empty($dto->chairperson_id)) {
                    $user = (new User())->find($dto->chairperson_id);
                    if ($user) {
                        $user->department_id = $department->id;
                        (new UserService())->updateUser($user);
                    }
                }

                $this->logger->info('Bölüm eklendi', ['id' => $department->id]);
                return $department->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu birimde bu isimde bir bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Mevcut bölümü günceller.
     *
     * @param Department $department Güncellenmiş Department nesnesi
     * @return int Bölümün ID'si
     * @throws Exception Duplicate isim veya güncelleme hatası
     */
    public function updateDepartment(Department $department): int
    {
        $this->logger->debug('Bölüm güncelleniyor', ['id' => $department->id]);

        try {
            return Database::transaction(function () use ($department) {
                $department->update();

                if (!empty($department->chairperson_id)) {
                    $user = (new User())->find($department->chairperson_id);
                    if ($user) {
                        $user->department_id = $department->id;
                        (new UserService())->updateUser($user);
                    }
                }

                $this->logger->info('Bölüm güncellendi', ['id' => $department->id]);
                return $department->id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu birimde bu isimde bir bölüm zaten kayıtlı. Lütfen farklı bir isim giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Bölümü sistemden siler.
     *
     * @param Department $department Silinecek bölüm nesnesi
     * @throws Exception
     */
    public function deleteDepartment(Department $department): void
    {
        $this->logger->debug('Bölüm siliniyor', ['id' => $department->id]);

        $programs = (new Program())->get()->where(['department_id' => $department->id])->all();
        if (!empty($programs)) {
            $programNames = array_map(fn($p) => $p->name, $programs);
            throw new Exception(
                "Bu bölüme bağlı programlar (" . implode(', ', $programNames) .
                ") bulunmaktadır. Bölümü silmek için önce bu programları silmeli veya başka bir bölüme taşımalısınız."
            );
        }

        try {
            Database::transaction(function () use ($department) {
                $users = (new User())->get()->where(['department_id' => $department->id])->all();
                foreach ($users as $user) {
                    $user->department_id = null;
                    $user->update();
                }

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

    /**
     * Birden fazla bölümü toplu siler.
     *
     * @param BulkDeleteDTO|array $dtoOrIds
     * @return BulkActionResultDTO
     */
    public function bulkDelete(BulkDeleteDTO|array $dtoOrIds): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkDeleteDTO ? $dtoOrIds : new BulkDeleteDTO(ids: array_map('intval', (array)$dtoOrIds));
        $this->logger->debug('Toplu bölüm silme başlatıldı', ['ids' => $dto->ids]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
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
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }

    /**
     * Birden fazla bölümü toplu günceller.
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

        $this->logger->debug('Toplu bölüm güncelleme başlatıldı', ['ids' => $dto->ids, 'fields' => $dto->fields]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
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

                foreach ($dto->fields as $fieldName => $fieldValue) {
                    $department->{$fieldName} = $fieldValue === '' ? null : $fieldValue;
                }

                $this->updateDepartment($department);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu bölüm güncelleme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }
}
