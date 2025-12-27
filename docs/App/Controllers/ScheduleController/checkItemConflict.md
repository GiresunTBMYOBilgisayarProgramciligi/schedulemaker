[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **checkItemConflict**

---
# ScheduleController::checkItemConflict(array $itemData)

Tek bir program öğesinin tüm paydaşlar için çakışma kontrolünü yapan "bekçi" fonksiyondur.

## İşleyiş
1.  Gelen `itemData` içindeki `lesson_id`, `lecturer_id` ve `classroom_id` üzerinden ilgili modelleri çeker.
2.  Dersin bağlı olduğu **Program**'ı tespit eder.
3.  Şu takvimleri (`Schedule`) tarar:
    *   Hocanın şahsi takvimi.
    *   Sınıfın doluluk takvimi.
    *   Programın (Öğrencilerin) ders takvimi.
    *   Dersin kendi koduna ait özel takvim.
4.  Herhangi birinde `resolveConflict` hatası alınırsa işlemi durdurur.

## Teknik Not
Bu metod, `saveScheduleItems` işleminden bağımsız olarak, sadece kontrol amaçlı (`checkScheduleCrash` üzerinden) da çağrılabilir.
