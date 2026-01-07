[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **importUsersFromExcel**

---
# ImportExportManager::importUsersFromExcel()

Excel dosyasındaki kullanıcı bilgilerini okuyarak veritabanına kaydeder veya mevcut kullanıcıları günceller.

## Mantık (Algoritma)
1.  **Başlık Doğrulama**: Excel'in ilk satırını okur ve "Mail", "Ünvanı", "Adı" gibi zorunlu başlıkları kontrol eder.
2.  **Veritabanı İşlemi (Transaction)**: Tüm süreç bir transaction içine alınır.
3.  **Satır Döngüsü**:
    - **Boş Satır Kontrolü**: Tamamen boş satırları atlar.
    - **Veri Doğrulama**: Mail, Ad, Soyad gibi zorunlu alanların doluluğunu kontrol eder.
    - **Caching**: Bölüm ve Program bilgilerini veritabanından bir kez çekip cache'te tutar.
4.  **Kullanıcı Kayıt/Güncelleme**: 
    - Mail adresi üzerinden kullanıcıyı sistemde arar (Caching kullanılır).
    - Kullanıcı varsa: Verileri günceller.
    - Kullanıcı yoksa: Yeni kayıt oluşturur.
5.  **Bitiş**: İşlem başarılıysa `commit()`, hata oluşursa `rollBack()` yapılır.
6.  **Raporlama**: Eklendi/Güncellendi sayılarını ve hata listesini döndürür.
