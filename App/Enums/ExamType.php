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
     * URL parametrelerindeki kısa/Türkçe adları (vize, final, but vb.) sınav tipine çevirir.
     * Büyük/küçük harf, Türkçe karakter, boşluk ve alt çizgi farkları yok sayılır.
     */
    public static function fromAlias(string $alias): ?self
    {
        $normalized = strtr(mb_strtolower(trim($alias), 'UTF-8'), [
            "\u{0307}" => '', // "İ" küçültülünce oluşan birleşik nokta
            'ı' => 'i', 'ü' => 'u', 'ş' => 's', 'ç' => 'c', 'ğ' => 'g', 'ö' => 'o',
            '_' => '-', ' ' => '-',
        ]);

        return match($normalized) {
            'midterm-exam', 'midterm', 'vize', 'ara-sinav', 'arasinav' => self::MIDTERM,
            'final-exam', 'final', 'final-sinavi', 'final-sinav' => self::FINAL,
            'makeup-exam', 'makeup', 'butunleme', 'but', 'butunleme-sinavi' => self::MAKEUP,
            default => null,
        };
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
