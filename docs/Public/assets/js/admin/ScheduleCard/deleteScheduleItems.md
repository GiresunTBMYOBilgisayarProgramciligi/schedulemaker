[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **deleteScheduleItems**

---
# ScheduleCard.deleteScheduleItems(ids)

Belirtilen ID listesine sahip ders kayıtlarını sistemden tamamen siler.

## Mantık (Algoritma)
1.  **Onay Mekanizması**: İşlem öncesinde kullanıcıdan genellikle bir onay alır.
2.  **AJAX İsteği**: `/ajax/delete-schedule-items` adresine silinecek ID'leri içeren bir dizi gönderir.
3.  **UI Güncelleme**: Sunucudan başarılı yanıt gelirse:
    - `clearTableItemsByIds()` metodunu çağırarak silinen veya parçalanan eski dersleri tablodan görsel olarak kaldırır.
    - `syncTableItems()` metodunu çağırarak, silme işlemi sonucunda oluşan yeni parçaları (split) veya güncellenen durumları tabloya işler.
4.  **Geri Dönüş**: Silinen derslerin bilgilerini kullanarak bu dersleri tekrar sol taraftaki "Müsait Dersler" listesine ekler (eğer program tipine uygunsa).
