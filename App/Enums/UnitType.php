<?php

namespace App\Enums;

/**
 * Üst birim tiplerini temsil eden Backed Enum.
 * (Fakülte, Enstitü, MYO vb.)
 */
enum UnitType: string
{
    case Faculty    = 'fakulte';
    case Institute  = 'enstitu';
    case Vocational = 'myo';
    case School     = 'yuksekokul';
    case Rectorate  = 'rektorluk';

    /**
     * Arayüzde gösterilecek Türkçe etiketleri döndürür.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Faculty    => 'Fakülte',
            self::Institute  => 'Enstitü',
            self::Vocational => 'Meslek Yüksekokulu',
            self::School     => 'Yüksekokul',
            self::Rectorate  => 'Rektörlük',
        };
    }

    /**
     * Birim türüne uygun yönetici unvanını (Dekan, Müdür, Rektör) döndürür.
     */
    public function getManagerTitle(): string
    {
        return match ($this) {
            self::Faculty   => 'Dekan',
            self::Rectorate => 'Rektör',
            self::Institute, self::Vocational, self::School => 'Müdür',
        };
    }

    /**
     * Birim türüne uygun yönetici yardımcısı unvanını (Dekan Yardımcısı / Dekan Yardımcıları vb.) döndürür.
     */
    public function getSubManagerTitle(bool $plural = false): string
    {
        $suffix = $plural ? 'Yardımcıları' : 'Yardımcısı';
        return match ($this) {
            self::Faculty   => 'Dekan ' . $suffix,
            self::Rectorate => 'Rektör ' . $suffix,
            self::Institute, self::Vocational, self::School => 'Müdür ' . $suffix,
        };
    }

    /**
     * Tüm tipleri value => label formatında dizi olarak döndürür (form select için).
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[] = ['value' => $case->value, 'label' => $case->getLabel()];
        }
        return $result;
    }

    /**
     * Label üzerinden Enum örneğini döndürür.
     */
    public static function fromLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getLabel() === $label) {
                return $case;
            }
        }
        return null;
    }
}
