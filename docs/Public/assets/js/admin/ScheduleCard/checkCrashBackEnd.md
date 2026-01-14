[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **checkCrashBackEnd**

---
# ScheduleCard.checkCrashBackEnd(scheduleItems)

Oluşturulan ders kayıtlarının sunucu tarafındaki (veritabanı) kurallara (hoca meşguliyeti, diğer programlardaki çakışmalar vb.) uygunluğunu kontrol etmek için AJAX isteği gönderir.

## Mantık (Algoritma)
1.  **Veri Hazırlığı**: Kontrol edilecek `ScheduleItem` nesnelerini JSON formatına çevirerek `FormData` içine ekler.
2.  **İstek Gönderimi**: `/ajax/checkScheduleCrash` adresine POST isteği atar.
3.  **Yanıt İşleme**:
    - Sunucudan gelen `status` değerine bakar.
    - Eğer "error" dönerse, hata mesajını `Toastr` ile kullanıcıya gösterir ve `false` döner.
    - Başarılıysa (çakışma yoksa) `true` döner.
4.  **Hata Yönetimi**: Ağ hatası veya sunucu hatası durumunda kullanıcıyı bilgilendirir.
