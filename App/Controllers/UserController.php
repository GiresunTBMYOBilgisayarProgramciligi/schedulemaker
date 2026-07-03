<?php

namespace App\Controllers;

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
        try {
            Gate::authorizeRole("submanager", false, "Kullanıcı oluşturma yetkiniz yok");

            // 1. Doğrulama ve DTO oluşturma
            $dto = (new UserValidator())->getDTO($requestData);

            // 3. Service'e gönder
            (new UserService())->saveNew($dto);

            return [
                "status" => "success",
                "msg" => "Kullanıcı başarıyla eklendi."
            ];

        } catch (ValidationException $e) {
            return [
                "status" => "error",
                "msg" => "Veri doğrulama hatası",
                "errors" => $e->getValidationErrors()
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Kullanıcı bilgilerini günceller (POST /ajax/user/update veya benzeri rotalar üzerinden)
     */
    public function update(array $requestData): array
    {
        try {
            // İzin kontrolü için mevcut kullanıcıyı bul
            if (empty($requestData['id'])) {
                throw new Exception("Güncellenecek kullanıcı ID'si belirtilmedi.");
            }
            
            $user = (new UserRepository())->find($requestData['id']);
            if (!$user) {
                throw new Exception("Kullanıcı bulunamadı.");
            }

            Gate::authorize("update", $user, "Kullanıcı bilgilerini güncelleme yetkiniz yok");

            // 1. Doğrulama ve DTO oluşturma
            $dto = (new UserValidator())->getDTO($requestData);
            
            // 3. Service'e gönder (Fill işlemi servise devredildi)
            (new UserService())->updateUserData($requestData['id'], $dto);

            return [
                "status" => "success",
                "msg" => "Kullanıcı başarıyla güncellendi."
            ];

        } catch (ValidationException $e) {
            return [
                "status" => "error",
                "msg" => "Veri doğrulama hatası",
                "errors" => $e->getValidationErrors()
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Kullanıcıyı siler (POST /ajax/user/delete veya benzeri rotalar üzerinden)
     */
    public function destroy(array $requestData): array
    {
        try {
            if (empty($requestData['id'])) {
                throw new Exception("Silinecek kullanıcı ID'si belirtilmedi.");
            }

            $user = (new UserRepository())->find($requestData['id']);
            if (!$user) {
                throw new Exception("Kullanıcı bulunamadı.");
            }

            Gate::authorize("delete", $user, "Kullanıcı Silme yetkiniz yok");

            // Servis üzerinden silme (Önce ders programları, sonra kullanıcı)
            (new UserService())->deleteUser($user);

            return [
                "status" => "success",
                "msg" => "Kullanıcı başarıyla silindi."
            ];

        } catch (ValidationException $e) {
            return [
                "status" => "error",
                "msg" => "Veri doğrulama hatası",
                "errors" => $e->getValidationErrors()
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }

    /**
     * Excel dosyasından kullanıcıları içe aktarır
     * @param array $files Yüklenen dosyalar
     * @return array
     */
    public function importUsers(array $files): array
    {
        try {
            $uploadedFile = $files['file'] ?? null;
            if (!$uploadedFile) {
                throw new Exception("Dosya yüklenmedi");
            }

            $spreadsheet = IOFactory::load($uploadedFile['tmp_name']);
            $importer    = new UserImporter($spreadsheet);
            $result      = $importer->import();

            return [
                'status' => "success",
                'msg'    => sprintf(
                    "%d kullanıcı oluşturuldu,%d kullanıcı güncellendi. %d hatalı kayıt var",
                    $result['added'], $result['updated'], $result['errorCount']
                ),
                'errors' => $result['errors']
            ];
        } catch (ValidationException $e) {
            return [
                "status" => "error",
                "msg" => "Veri doğrulama hatası",
                "errors" => $e->getValidationErrors()
            ];
        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }
}