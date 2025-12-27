[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **generateEmptyWeek**

---
# ScheduleController::generateEmptyWeek(string $type, ?int $maxDayIndex)

Ders programı tablosunun iskeletini (boş haftalık yapıyı) oluşturur.

## Parametreler
*   `$type`: 'html' veya 'excel' formatını belirler.
*   `$maxDayIndex`: Haftanın kaçıncı gününe kadar (örn: 5 gün) oluşturulacağını belirler.

## İşleyiş
1.  `getSettingValue` ile veritabanından `day_count` ve saat dilimleri (`schedule_times`) bilgilerini çeker.
2.  Her bir saat dilimi için bir satır oluşturur.
3.  Satırın içine her bir gün için boş bir hücre (`empty-slot`) yerleştirir.
4.  Oluşan matrisi dizi olarak döner.

## Kullanım Alanı
Tablo render edilmeden önce `prepareScheduleRows` içinde temel yapı olarak kullanılır.
