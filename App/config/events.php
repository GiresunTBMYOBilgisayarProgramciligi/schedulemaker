<?php

use App\Core\EventDispatcher;
use App\Events\UserForgotPasswordEvent;
use App\Listeners\SendPasswordResetEmailListener;
use App\Events\ScheduleNoteStatusUpdatedEvent;
use App\Listeners\SendScheduleNoteFeedbackEmailListener;
use App\Events\ChairpersonChangedEvent;
use App\Listeners\SyncChairpersonRoleListener;
use App\Events\ScheduleChangesNotifiedEvent;
use App\Listeners\SendScheduleChangesEmailListener;
use App\Events\SchedulePublishedEvent;
use App\Listeners\SendSchedulePublishedEmailListener;
use App\Events\LessonAssignedEvent;
use App\Listeners\SyncLecturerAffiliationsListener;

use App\Events\ScheduleNoteDeletedEvent;
use App\Listeners\SendScheduleNoteDeletedEmailListener;

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
    ScheduleNoteDeletedEvent::class,
    SendScheduleNoteDeletedEmailListener::class
);

$dispatcher->listen(
    ChairpersonChangedEvent::class,
    SyncChairpersonRoleListener::class
);

$dispatcher->listen(
    ScheduleChangesNotifiedEvent::class,
    SendScheduleChangesEmailListener::class
);

$dispatcher->listen(
    SchedulePublishedEvent::class,
    SendSchedulePublishedEmailListener::class
);

$dispatcher->listen(
    LessonAssignedEvent::class,
    SyncLecturerAffiliationsListener::class
);

