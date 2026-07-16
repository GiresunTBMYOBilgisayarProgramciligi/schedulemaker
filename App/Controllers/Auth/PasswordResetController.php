<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Validators\Auth\ForgotPasswordValidator;
use App\Validators\Auth\ResetPasswordValidator;
use App\Services\Auth\PasswordResetService;
use App\Exceptions\ValidationException;
use Exception;

class PasswordResetController extends Controller
{
    /**
     * Şifremi unuttum e-postası gönderme işlemi (AJAX veya Form)
     */
    public function forgotPassword(array $data): array
    {
        try {
            $validator = new ForgotPasswordValidator();
            $dto = $validator->getDTO($data);

            $service = new PasswordResetService();
            $service->sendResetLink($dto);

            return [
                "status" => "success",
                "msg" => "Şifre sıfırlama bağlantısı e-posta adresinize gönderildi."
            ];
        } catch (ValidationException $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage(),
                "errors" => $e->getErrors()
            ];
        } catch (Exception $e) {
            $this->logger()->error("Şifre sıfırlama bağlantısı gönderilirken hata", $this->logContext(['error' => $e->getMessage()]));
            return [
                "status" => "error",
                "msg" => "Bir hata oluştu, lütfen daha sonra tekrar deneyin."
            ];
        }
    }

    /**
     * Yeni şifre belirleme işlemi
     */
    public function resetPassword(array $data): array
    {
        try {
            $validator = new ResetPasswordValidator();
            $dto = $validator->getDTO($data);

            $service = new PasswordResetService();
            $service->resetPassword($dto);

            return [
                "status" => "success",
                "msg" => "Şifreniz başarıyla sıfırlandı. Yeni şifrenizle giriş yapabilirsiniz.",
                "redirect" => "/auth/login"
            ];
        } catch (ValidationException $e) {
            return [
                "status" => "error",
                "msg" => $e->getMessage(),
                "errors" => $e->getErrors()
            ];
        } catch (Exception $e) {
            $this->logger()->error("Şifre sıfırlanırken hata", $this->logContext(['error' => $e->getMessage()]));
            return [
                "status" => "error",
                "msg" => $e->getMessage()
            ];
        }
    }
}
