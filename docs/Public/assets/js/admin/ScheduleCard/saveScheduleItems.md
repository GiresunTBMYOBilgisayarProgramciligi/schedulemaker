[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **saveScheduleItems**

---
# ScheduleCard.saveScheduleItems(items)

Oluşturulan veya düzenlenen ders/sınav verilerini veritabanına kaydetmek üzere sunucuya gönderir.

## Mantık (Algoritma)
1.  **Serileştirme**: Parametre olarak gelen `ScheduleItem` objelerini JSON formatına hazırlar.
2.  **AJAX İsteği**: `/ajax/save-schedule-items` endpoint'ine POST isteği gönderir.
3.  **Başarı Durumu**:
    - Kayıt başarılıysa sunucudan dönen kalıcı ID'leri tabloya yansıtır (`syncTableItems`).
    - İşlem yapılan dersleri yan listeden (available list) görsel olarak kaldırır.
    - Başarı mesajı gösterir.
4.  **Hata Durumu**: Hata detaylarını kullanıcıya `Toastr` ile bildirir ve gerekirse tabloyu eski haline döndürür.
