[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **availableClassrooms**

---
# ScheduleController::availableClassrooms(array $filters)

Belirli bir zaman diliminde müsait olan dersliklerin listesini döndürür. Bu metot, dersin parçalı (fragmented) yapısını destekler.

## Filtreler
*   `schedule_id`, `items`, `lesson_id`, `day_index`, `week_index` (Zorunlu)
*   `hours`, `startTime` (Opsiyonel - Geriye dönük uyumluluk için)
*   `items`: JSON formatında eklenecek dersin parçalarını (`start_time`, `end_time`) içeren dizi.

## İşleyiş
1.  Tüm aktif derslikleri (`Classroom`) listeler.
2.  Her bir aday derslik için, o dersliğin programındaki mevcut kayıtlar (`ScheduleItem`) getirilir.
3.  **Parçalı Uygunluk Kontrolü**:
    *   `ScheduleCard.js` tarafından gönderilen her bir ders parçası (`items`) için ayrı ayrı çakışma kontrolü yapılır.
    *   Eğer dersin *herhangi bir parçası* dersliğin mevcut bir kaydıyla çakışıyorsa (`checkOverlap`), derslik listeden çıkarılır.
    *   Bu sayede öğle arası gibi engelli slotlar atlanarak hesaplama yapılır.
    *   **İstisna**: "Uzaktan Eğitim Sınıfı" (type: 3 - UZEM) tipi sınıflar veya UZEM tipindeki dersler doluluk kontrolünden muaftır ve her zaman müsait kabul edilir. Zira bu dersler derslik programına kaydedilmez.
4.  LİMİT: Sınıfın kapasitesi ve tipi (`uzem`, `lab` vb.) dersin gereksinimleriyle karşılaştırılır.

## Dönüş Değeri
*   `array`: Müsait olan dersliklerin tam listesi.
