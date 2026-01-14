[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **importLessonsFromExcel**

---
# ImportExportManager::importLessonsFromExcel()

Excel dosyasındaki ders bilgilerini okuyarak veritabanına kaydeder.

## Mantık (Algoritma)
1.  **Başlık Doğrulama**: Excel'in ilk satırını okur, başlıkları temizler ve beklenen formatı kontrol eder.
2.  **Veritabanı İşlemi (Transaction)**: Tüm içe aktarma süreci bir veritabanı işlemi (transaction) içine alınır. Hata durumunda değişiklikler geri alınır.
3.  **Satır Döngüsü**: Verilerin olduğu her bir satır için:
    - **Boş Satır Kontrolü**: Tamamen boş olan satırları atlar.
    - **Varlık Kontrolü ve Caching**: Bölüm, Program ve Hoca isimlerini sistemde arar. Performans için sonuçları bellek (cache) üzerinde tutar, böylece mükerrer veritabanı sorgularını önler.
    - **Hata Yakalama**: Eğer hoca veya bölüm bulunamazsa, satırı atlar ve hata listesine ekler.
4.  **Tekillik Denetimi**: Ders kodu, program ID ve grup numarası kombinasyonuyla dersin daha önce kaydedilip kaydedilmediğine bakar.
5.  **Kayıt/Güncelleme**: 
    - Ders varsa: Mevcut kaydı Excel'deki yeni verilerle günceller.
    - Ders yoksa: Yeni bir `Lesson` modeli oluşturup kaydeder.
6.  **Bitiş**: Tüm satırlar başarıyla işlendiyse `commit()` yapılır, aksi takdirde `rollBack()` uygulanır.
7.  **Raporlama**: İşlem sonunda özet bilgileri ve varsa hata listesini döndürür.
