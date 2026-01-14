[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **availableClassrooms**

---
# ScheduleController::availableClassrooms(array $filters)

Belirli bir zaman diliminde müsait olan dersliklerin listesini döndürür.

## Filtreler
*   `semester_no`, `day_index`, `start_time`, `end_time`, `classroom_type` vb.

## İşleyiş
1.  Tüm aktif derslikleri (`Classroom`) listeler.
2.  Belirtilen zaman dilimi için `schedule_items` tablosunu sorgular.
3.  Eğer bir derslik o saatte başka bir dersle veya sınavla (`ScheduleItem`) çakışıyorsa, listeden çıkarılır.
    *   **İstisna**: "Uzaktan Eğitim Sınıfı" (type: 3 - UZEM) tipi sınıflar veya UZEM tipindeki dersler doluluk kontrolünden muaftır ve her zaman müsait kabul edilir. Zira bu dersler derslik programına kaydedilmez.
4.  LİMİT: Sınıfın kapasitesi ve tipi (`uzem`, `lab` vb.) dersin gereksinimleriyle karşılaştırılır.

## Dönüş Değeri
*   `array`: Müsait olan dersliklerin tam listesi.
