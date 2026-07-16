<?php

namespace App\Events;

use App\Models\User;

/**
 * Kullanıcı şifresini unuttuğunda fırlatılan olay (event).
 * 
 * Not: Bu sınıf PHP 8'in "Constructor Property Promotion" özelliğini kullanmaktadır.
 * public User $user tanımı hem property oluşturur hem de constructor'dan gelen değeri atar.
 * Bu nedenle sınıf içi "boş" görünür, fakat işlevseldir.
 */
class UserForgotPasswordEvent
{
    public function __construct(
        public User $user,
        public string $token
    ) {
    }
}
