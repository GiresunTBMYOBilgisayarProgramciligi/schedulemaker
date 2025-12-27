[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **availableLessons**

---
# ScheduleController::availableLessons(Schedule $schedule)

Henüz ders programı tamamlanmamış (yerleştirilecek saati kalan) derslerin listesini döner.

## İşleyiş
1.  Verilen `Schedule` (program) kaydına göre sistemdeki tüm dersleri tarar.
2.  Her bir ders için `ScheduleItem` tablosundaki mevcut kayıtları sayar / saatlerini toplar.
3.  Eğer dersin toplam saati, yerleştirilen saatten fazlaysa (`hours > placed_hours`), bu dersi "kullanılabilir" olarak listeye ekler.
4.  Grup dersleri için `group_no` bilgisini de dikkate alarak hesaplama yapar.

## Dönüş Değeri
*   `array`: Ders bilgilerini içeren (ID, kodu, adı, kalan saati vb.) nesneler dizisi.
