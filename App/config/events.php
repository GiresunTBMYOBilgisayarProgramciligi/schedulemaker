<?php

use App\Core\EventDispatcher;
use App\Events\UserForgotPasswordEvent;
use App\Listeners\SendPasswordResetEmailListener;

$dispatcher = EventDispatcher::getInstance();

// Tüm olay (event) ve dinleyici (listener) kayıtlarını buraya ekleyebilirsiniz.
// Örnek: WordPress'in add_action() fonksiyonuna benzer şekilde, 
// sistemin herhangi bir yerinden EventDispatcher::getInstance()->listen() çağrılarak da olaylar eklenebilir.

$dispatcher->listen(
    UserForgotPasswordEvent::class,
    SendPasswordResetEmailListener::class
);
