<?php

namespace App\Enums;

enum ExamType: string
{
    case MIDTERM = 'midterm-exam';
    case FINAL = 'final-exam';
    case MAKEUP = 'makeup-exam';

    public function label(): string
    {
        return match($this) {
            self::MIDTERM => 'Ara Sınav',
            self::FINAL   => 'Final Sınavı',
            self::MAKEUP  => 'Bütünleme Sınavı',
        };
    }

    /**
     * Tüm sınav tiplerinin string değerlerini döndürür.
     * DB sorguları ve in_array kontrolleri için kullanılır.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Verilen string'in geçerli bir sınav tipi olup olmadığını kontrol eder.
     */
    public static function isExamType(string $type): bool
    {
        return self::tryFrom($type) !== null;
    }

    /**
     * Sınav başlangıç tarih ayar anahtarını döndürür.
     */
    public function startDateSettingKey(): string
    {
        return match($this) {
            self::MIDTERM => 'midterm_start_date',
            self::FINAL   => 'final_start_date',
            self::MAKEUP  => 'makeup_start_date',
        };
    }
}
