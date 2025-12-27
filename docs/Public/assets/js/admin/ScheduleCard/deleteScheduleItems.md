[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **deleteScheduleItems**

---
# ScheduleCard.deleteScheduleItems(ids)

Belirtilen ID listesine sahip ders kayıtlarını sistemden tamamen siler.

## Mantık (Algoritma)
1.  **Onay Mekanizması**: İşlem öncesinde kullanıcıdan genellikle bir onay alır.
2.  **AJAX İsteği**: `/ajax/delete-schedule-items` adresine silinecek ID'leri içeren bir dizi gönderir.
3.  **UI Temizliği**: Sunucudan başarılı yanıt gelirse, `clearTableItemsByIds()` metodunu çağırarak bu dersleri tablodan görsel olarak kaldırır.
4.  **Geri Dönüş**: Silinen derslerin bilgilerini (id, code, names) kullanarak bu dersleri tekrar sol taraftaki "Müsait Dersler" listesine ekler.
