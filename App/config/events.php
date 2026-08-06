<?php

use App\Core\EventDispatcher;
use App\Events\UserForgotPasswordEvent;
use App\Listeners\SendPasswordResetEmailListener;

use App\Events\ScheduleNoteStatusUpdatedEvent;
use App\Listeners\SendScheduleNoteFeedbackEmailListener;

use App\Events\ChairpersonChangedEvent;
use App\Listeners\SyncChairpersonRoleListener;

$dispatcher = EventDispatcher::getInstance();

// Tüm olay (event) ve dinleyici (listener) kayıtlarını buraya ekleyebilirsiniz.

$dispatcher->listen(
    UserForgotPasswordEvent::class,
    SendPasswordResetEmailListener::class
);

$dispatcher->listen(
    ScheduleNoteStatusUpdatedEvent::class,
    SendScheduleNoteFeedbackEmailListener::class
);

$dispatcher->listen(
    ChairpersonChangedEvent::class,
    SyncChairpersonRoleListener::class
);

use App\Events\ScheduleChangesNotifiedEvent;
use App\Listeners\SendScheduleChangesEmailListener;

$dispatcher->listen(
    ScheduleChangesNotifiedEvent::class,
    SendScheduleChangesEmailListener::class
);
