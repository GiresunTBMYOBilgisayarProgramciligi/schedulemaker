[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **availableObservers**

---
# ScheduleController::availableObservers(array $filters)

Sınav programları için belirlenen zaman diliminde müsait olan gözetmenlerin (Hocaların) listesini döner. Bu metot, sınavın parçalı (fragmented) yapısını destekler.

## Filtreler
*   `type`, `items`, `day_index`, `week_index` (Zorunlu)
*   `hours`, `startTime` (Opsiyonel - Geriye dönük uyumluluk için)
*   `items`: JSON formatında eklenecek sınavın parçalarını (`start_time`, `end_time`) içeren dizi.

## İşleyiş
1.  Tüm aktif öğretim üyelerini (`User`) listeler.
2.  Her bir aday hoca için o dönemdeki mevcut programı (`ScheduleItem`) getirilir.
3.  **Parçalı Uygunluk Kontrolü**:
    *   İstenen her bir sınav parçası (`items`) için hocanın mevcut kayıtlarıyla çakışma kontrolü yapılır.
    *   Eğer hoca o saatte başka bir sınavda gözetmen ise veya dersi varsa "meşgul" kabul edilir.
    *   Tüm parçalar müsaitse hoca "müsait" olarak işaretlenir.
4.  Çıktı, Frontend'deki seçici (select) elementine uygun formatta döner.

## Dönüş Değeri
*   `array`: Hoca ID ve Ad/Soyad bilgilerini içeren liste.
