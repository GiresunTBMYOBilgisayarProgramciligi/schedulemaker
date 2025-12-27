[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **fetchAvailableClassrooms**

---
# ScheduleCard.fetchAvailableClassrooms(day, startTime, duration, type)

Belirli bir zaman dilimi ve ders türü (Normal/Laboratuvar) için müsait olan sınıfları getirir.

## Mantık (Algoritma)
1.  **Parametre Hazırlığı**: Gün, saat, süre ve derslik türü (classroom_type) bilgilerini paketler.
2.  **AJAX İsteği**: `/ajax/get-available-classrooms` endpoint'ine POST isteği gönderir.
3.  **Filitreleme**: Sunucu tarafında o saatte dersi olmayan ve dersin türüne (teorik/pratik) uygun kapasiteye/ekipmana sahip sınıflar sorgulanır.
4.  **UI Güncelleme**: Gelen sınıflar listesi, atama modalı içerisindeki `classroom-select` dropdown'ına yerleştirilir.
