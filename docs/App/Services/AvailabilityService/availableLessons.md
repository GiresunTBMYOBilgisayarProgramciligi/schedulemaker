[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Services](../README.md) / [AvailabilityService](README.md) / **availableLessons**

---
# AvailabilityService::availableLessons(Schedule $schedule, bool $preferenceMode = false)

Henüz ders programı tamamlanmamış (yerleştirilecek saati kalan) derslerin listesini döner.

## İşleyiş
1.  Verilen `Schedule` (program) kaydına göre sistemdeki tüm dersleri tarar.
2.  Her bir ders için `ScheduleItem` tablosundaki mevcut kayıtları sayar / saatlerini toplar.
3.  Eğer dersin toplam saati, yerleştirilen saatten fazlaysa (`hours > placed_hours`), bu dersi "kullanılabilir" olarak listeye ekler.
4.  Grup dersleri için `group_no` bilgisini de dikkate alarak hesaplama yapar.
5.  `preferenceMode` aktifse, sadece tercih ve kapalı saat kartlarını (dummy) döner.

## Parametreler
- `Schedule $schedule`: Program nesnesi.
- `bool $preferenceMode`: Tercih modu (varsayılan: false).

## Dönüş Değeri
*   `array`: Ders bilgilerini içeren (ID, kodu, adı, kalan saati vb.) nesneler dizisi.
