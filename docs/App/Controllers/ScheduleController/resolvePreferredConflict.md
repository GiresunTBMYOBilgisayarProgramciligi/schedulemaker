[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **resolvePreferredConflict**

---
# ScheduleController::resolvePreferredConflict(...)

"Tercih Edilen" (`preferred`) bir zaman dilimi üzerine gerçek bir ders eklendiğinde, tercih edilen alanı "cerrahi" olarak bölen veya daraltan algoritmadır.

## Algoritma Senaryoları

1.  **Tam Kapsama**: Yeni ders, tercih edilen alanın tamamını kaplıyorsa; tercih edilen alan silinir.
2.  **Sol/Sağ Daraltma**: Yeni ders, alanın sadece başından veya sonundan bir kısmıyla çakışıyorsa; tercih edilen alanın `start_time` veya `end_time` bilgisi güncellenerek alan daraltılır.
3.  **Ortadan Bölme**: Yeni ders, tercih edilen alanın tam ortasına denk geliyorsa; orijinal alan ikiye bölünür (Ders öncesi ve ders sonrası iki ayrı `preferred` alan oluşur).

## Dönüş Değeri
*   `void`: Mevcut kayıtları veritabanında günceller veya siler.
