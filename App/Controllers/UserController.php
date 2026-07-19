<?php

namespace App\Controllers;

use App\Enums\PermissionType;

use App\Core\Controller;
use App\Models\User;
use App\Core\Gate;
use App\Services\UserService;
use App\Validators\UserValidator;
use App\DTOs\UserDTO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Import\UserImporter;
use App\Repositories\UserRepository;
use Exception;
use App\Exceptions\ValidationException;

class UserController extends Controller
{
    protected string $table_name = "users";
    protected string $modelName = "App\Models\User";


    /**
     * Yeni kullanıcı oluşturur (POST /ajax/user/add veya benzeri rotalar üzerinden tetiklenecek)
     * 
     * @param array $requestData Rota tarafından gönderilen POST verisi
     * @return array JSON formatında dönülecek yanıt dizisi
     */
    public function store(array $requestData): array
    {
        Gate::authorizeRole("submanager", false, "Kullanıcı oluşturma yetkiniz yok");

        // 1. Doğrulama ve DTO oluşturma
        $dto = (new UserValidator())->getDTO($requestData);

        // 3. Service'e gönder
        $userId = (new UserService())->saveNew($dto);

        return [
            "status" => "success",
            "msg" => "Kullanıcı başarıyla eklendi."
        ];
    }

    /**
     * Kullanıcı bilgilerini günceller (POST /ajax/user/update veya benzeri rotalar üzerinden)
     */
    public function update(array $requestData): array
    {            // İzin kontrolü için mevcut kullanıcıyı bul
            if (empty($requestData['id'])) {
                throw new Exception("Güncellenecek kullanıcı ID'si belirtilmedi.");
            }
            
            $user = (new UserRepository())->find($requestData['id']);
            if (!$user) {
                throw new Exception("Kullanıcı bulunamadı.");
            }

            Gate::authorize(PermissionType::UPDATE->value, $user, "Kullanıcı bilgilerini güncelleme yetkiniz yok");

            $currentUser = \App\Middlewares\AuthMiddleware::user();
            $canEditSpecialFields = \App\Core\Gate::allowsRole('submanager') || ($currentUser->role === 'department_head' && $currentUser->id !== $user->id);

            if (!$canEditSpecialFields) {
                $requestData['role'] = $user->role;
                $requestData['title'] = $user->title;
                $requestData['department_id'] = $user->department_id;
                $requestData['program_id'] = $user->program_id;
            }

            // 1. Doğrulama ve DTO oluşturma
            $dto = (new UserValidator())->getDTO($requestData);
            
            // 3. Service'e gönder (Fill işlemi servise devredildi)
            (new UserService())->updateUserData($requestData['id'], $dto);

            return [
                "status" => "success",
                "msg" => "Kullanıcı başarıyla güncellendi."
            ];
    }

    /**
     * Kullanıcıyı siler (POST /ajax/user/delete veya benzeri rotalar üzerinden)
     */
    public function destroy(array $requestData): array
    {            if (empty($requestData['id'])) {
                throw new Exception("Silinecek kullanıcı ID'si belirtilmedi.");
            }

            $user = (new UserRepository())->find($requestData['id']);
            if (!$user) {
                throw new Exception("Kullanıcı bulunamadı.");
            }

            Gate::authorize(PermissionType::DELETE->value, $user, "Kullanıcı Silme yetkiniz yok");

            // Servis üzerinden silme (Önce ders programları, sonra kullanıcı)
            (new UserService())->deleteUser($user);

            return [
                "status" => "success",
                "msg" => "Kullanıcı başarıyla silindi."
            ];
    }

    /**
     * Excel dosyasından kullanıcıları içe aktarır
     * @param array $files Yüklenen dosyalar
     * @return array
     */
    public function importUsers(array $files): array
    {            $uploadedFile = $files['file'] ?? null;
            if (!$uploadedFile) {
                throw new Exception("Dosya yüklenmedi");
            }

            $spreadsheet = IOFactory::load($uploadedFile['tmp_name']);
            $importer    = new UserImporter($spreadsheet);
            $result      = $importer->import();

            return [
                'status'       => "success",
                'msg'          => sprintf(
                    "%d kullanıcı oluşturuldu,%d kullanıcı güncellendi. %d hatalı kayıt var",
                    $result['added'], $result['updated'], $result['errorCount']
                ),
                'errors'       => $result['errors'],
                'addedUsers'   => $result['addedUsers'],
                'updatedUsers' => $result['updatedUsers']
            ];
    }

    /**
     * @param int $unitId
     * @return array
     * @throws Exception
     */
    public function getLecturersByUnitResponse(int $unitId): array
    {
        $lecturers = (new UserRepository())->getLecturersByUnit($unitId);

        $lecturersList = [];
        foreach ($lecturers as $lecturer) {
            $lecturersList[] = [
                'id' => $lecturer->id,
                'name' => $lecturer->getFullName()
            ];
        }

        return [
            'status' => 'success',
            'lecturers' => $lecturersList
        ];
    }
}