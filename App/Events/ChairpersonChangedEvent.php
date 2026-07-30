<?php

namespace App\Events;

/**
 * Bölüm başkanı değiştiğinde (atanma, değiştirme veya kaldırma) tetiklenen olay.
 */
class ChairpersonChangedEvent
{
    public function __construct(
        public ?int $oldChairpersonId,
        public ?int $newChairpersonId
    ) {
    }
}
