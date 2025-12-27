[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **lessonHourToMinute**

---
# ScheduleController::lessonHourToMinute($scheduleType, $hours)

Ders saat sayısını (örn: 2 saat) gerçek zaman değerine (dakika) çevirir.

## İşleyiş
1.  Ayarlar tablosundan (`settings`) şu değerleri okur:
    *   `lesson_duration`: Bir dersin süresi (örn: 50 dk).
    *   `break_time`: Teneffüs süresi (örn: 10 dk).
2.  Toplam Süre Hesabı: `(hours * lesson_duration) + ((hours - 1) * break_time)`.
3.  Eğer son dersin sonuna teneffüs eklenmek istenmiyorsa (standart akış) `(hours - 1)` kullanılır.

## Kullanım Alanı
Kayıt (`saveScheduleItems`) ve silme (`processItemDeletion`) sırasında blokların bitiş saatinin hesaplanmasında kritik rol oynar.
