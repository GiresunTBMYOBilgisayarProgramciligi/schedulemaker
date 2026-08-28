<?php

namespace App\DTOs;

use ArrayAccess;

/**
 * Excel ve ICS program dışa aktarma gösterim seçeneklerini taşıyan kesin tipli DTO.
 */
class ScheduleExportOptionsDTO implements ArrayAccess
{
    public bool $show_code;
    public bool $show_lecturer;
    public bool $show_program;
    public bool $show_observer;

    public function __construct(
        public bool $showCode = true,
        public bool $showLecturer = true,
        public bool $showProgram = true,
        public bool $showObserver = true
    ) {
        $this->show_code     = $this->showCode;
        $this->show_lecturer = $this->showLecturer;
        $this->show_program  = $this->showProgram;
        $this->show_observer = $this->showObserver;
    }

    public static function fromArray(array|self $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        return new self(
            showCode: isset($data['show_code']) ? filter_var($data['show_code'], FILTER_VALIDATE_BOOLEAN) : (isset($data['showCode']) ? (bool)$data['showCode'] : true),
            showLecturer: isset($data['show_lecturer']) ? filter_var($data['show_lecturer'], FILTER_VALIDATE_BOOLEAN) : (isset($data['showLecturer']) ? (bool)$data['showLecturer'] : true),
            showProgram: isset($data['show_program']) ? filter_var($data['show_program'], FILTER_VALIDATE_BOOLEAN) : (isset($data['showProgram']) ? (bool)$data['showProgram'] : true),
            showObserver: isset($data['show_observer']) ? filter_var($data['show_observer'], FILTER_VALIDATE_BOOLEAN) : (isset($data['showObserver']) ? (bool)$data['showObserver'] : true)
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

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->{$offset});
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->{$offset} ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->{$offset} = (bool)$value;
    }

    public function offsetUnset(mixed $offset): void
    {
    }
}
