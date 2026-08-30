<?php

namespace App\Models;

use App\Core\Model;
use DateTime;

/**
 * user_consents tablosundaki her bir kaydı temsil eden sınıf
 */
class UserConsent extends Model
{
    public ?int $id = null;
    public ?int $user_id = null;
    public ?string $consent_type = null;
    public ?string $version = 'v1.0';
    public ?string $ip_address = null;
    public ?string $user_agent = null;
    public ?DateTime $accepted_at = null;

    public ?User $user = null;

    protected array $dateFields = ['accepted_at'];
    protected array $excludeFromDb = ['user'];
    protected string $table_name = "user_consents";

    public function getLabel(): string
    {
        return "kullanıcı aydınlatma onayı";
    }

    public function getLogDetail(): string
    {
        return "Onay ID: {$this->id} (Kullanıcı ID: {$this->user_id}, Tip: {$this->consent_type}, Sürüm: {$this->version})";
    }
}
