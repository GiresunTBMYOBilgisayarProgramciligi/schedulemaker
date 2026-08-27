<?php

namespace App\DTOs;

/**
 * Excel ve ICS program dışa aktarma gösterim seçeneklerini taşıyan kesin tipli DTO.
 */
readonly class ScheduleExportOptionsDTO
{
    public function __construct(
        public bool $showCode = true,
        public bool $showLecturer = true,
        public bool $showProgram = true,
        public bool $showObserver = true
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            showCode: isset($data['show_code']) ? filter_var($data['show_code'], FILTER_VALIDATE_BOOLEAN) : true,
            showLecturer: isset($data['show_lecturer']) ? filter_var($data['show_lecturer'], FILTER_VALIDATE_BOOLEAN) : true,
            showProgram: isset($data['show_program']) ? filter_var($data['show_program'], FILTER_VALIDATE_BOOLEAN) : true,
            showObserver: isset($data['show_observer']) ? filter_var($data['show_observer'], FILTER_VALIDATE_BOOLEAN) : true
        );
    }

    public function toArray(): array
    {
        return [
            'show_code'     => $this->showCode,
            'show_lecturer' => $this->showLecturer,
            'show_program'  => $this->showProgram,
            'show_observer' => $this->showObserver,
        ];
    }
}
