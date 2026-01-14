[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **getSchedule**

---
# ScheduleCard.getSchedule()

Sunucudan ilgili programın (schedule) meta verilerini AJAX (fetch) ile çeker.

## Mantık (Algoritma)
1.  **ID Kontrolü**: `this.id` yoksa işlemi sonlandırır.
2.  **İstek Hazırlama**: `FormData` içine `id` bilgisini ekler.
3.  **Sunucu İletişimi**: `/ajax/getSchedule` adresine POST isteği atar.
4.  **Hata Yönetimi**: Eğer sunucudan hata mesajı gelirse `Toast` bildirimi gösterir.
5.  **Veri Dönüşü**: Başarılı ise `data.schedule` objesini döndürür; aksi halde `false` döner.
