[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **fetchAvailableObservers**

---
# ScheduleCard.fetchAvailableObservers(day, startTime, duration)

Sınav veya ders için o saat diliminde görevi olmayan (müsait) gözetmenleri/hocaları listeler.

## Mantık (Algoritma)
1.  **Giriş**: Gün, başlangıç saati ve süreyi girdi olarak alır.
2.  **AJAX İsteği**: `/ajax/get-available-observers` adresine istek gönderir.
3.  **Kural Kontrolü**: Sunucu, personelin o anki ders yükünü ve tercih kısıtlarını kontrol ederek uygun isimleri döner.
4.  **UI Güncelleme**: Dönen gözetmen listesi, ilgili seçim kutusuna (`observer-select`) doldurulur.
