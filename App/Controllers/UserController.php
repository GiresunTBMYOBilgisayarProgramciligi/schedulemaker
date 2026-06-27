<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Core\Gate;
use App\Services\UserService;
use App\Validators\UserValidator;
use App\DTOs\UserDTO;
use Exception;

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

            // 1. Validator'ı çalıştır
            $validator = new UserValidator();
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            // 2. DTO oluştur
            $dto = UserDTO::fromArray($requestData);

            // 3. Service'e gönder
            (new UserService())->saveNew($dto);

            return [
                "status" => "success",
                "msg" => "Kullanıcı başarıyla eklendi."
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
            
            $user = (new User())->find($requestData['id']);
            if (!$user) {
                throw new Exception("Kullanıcı bulunamadı.");
            }

            Gate::authorize("update", $user, "Kullanıcı bilgilerini güncelleme yetkiniz yok");

            // 1. Validator'ı çalıştır
            $validator = new UserValidator();
            $validationResult = $validator->validate($requestData);

            if (!$validationResult->isValid) {
                return [
                    "status" => "error",
                    "msg" => "Veri doğrulama hatası.",
                    "errors" => $validationResult->errors
                ];
            }

            // 2. DTO oluştur ve Model'e aktar
            $dto = UserDTO::fromArray($requestData);
            
            // Mevcut modele DTO verilerini dolduruyoruz
            $user->fill(array_merge(['id' => $requestData['id']], $dto->toArray()));

            // 3. Service'e gönder
            (new UserService())->updateUser($user);

            return [
                "status" => "success",
                "msg" => "Kullanıcı başarıyla güncellendi."
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

            $user = (new User())->find($requestData['id']);
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

        } catch (Exception $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }
}