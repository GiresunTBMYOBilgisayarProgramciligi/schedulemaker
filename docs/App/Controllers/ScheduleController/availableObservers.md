[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **availableObservers**

---
# ScheduleController::availableObservers(array $filters)

Sınav programları için belirlenen zaman diliminde müsait olan gözetmenlerin (Hocaların) listesini döner.

## İşleyiş
1.  Tüm aktif öğretim üyelerini (`User`) listeler.
2.  Belirtilen `day_index` ve `time` aralığı için `schedule_items` tablosunda bu hocaya ait bir ders veya sınav kaydı olup olmadığına bakar.
3.  Eğer hoca o saatte başka bir sınavda gözetmen değilse veya dersi yoksa "müsait" olarak işaretlenir.
4.  Çıktı, Frontend'deki seçici (select) elementine uygun formatta döner.

## Dönüş Değeri
*   `array`: Hoca ID ve Ad/Soyad bilgilerini içeren liste.
