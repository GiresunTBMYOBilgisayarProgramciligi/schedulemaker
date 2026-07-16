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
