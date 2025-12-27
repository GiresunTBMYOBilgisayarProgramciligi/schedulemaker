[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **importLessonsFromExcel**

---
# ImportExportManager::importLessonsFromExcel()

Excel dosyasındaki ders bilgilerini okuyarak veritabanına kaydeder.

## Mantık (Algoritma)
1.  **Başlık Doğrulama**: Excel'in ilk satırını okur ve "Bölüm", "Program", "Dersin Kodu" gibi zorunlu başlıkların doğru sırada olduğunu kontrol eder.
2.  **Satır Döngüsü**: Verilerin olduğu her bir satır için:
    - **Varlık Kontrolü**: Satırdaki Bölüm, Program ve Hoca isimlerini ilgili Controller'lar aracılığıyla sorgulayarak sistemdeki ID'lerini bulur.
    - **Hata Yakalama**: Eğer hoca veya bölüm bulunamazsa, satırı atlar ve bir hata mesajı oluşturur.
3.  **Tekillik Denetimi**: Ders kodu, program ID ve grup numarası kombinasyonuyla dersin daha önce kaydedilip kaydedilmediğine bakar.
4.  **Kayıt/Güncelleme**: 
    - Ders varsa: Mevcut kaydı Excel'deki yeni verilerle günceller.
    - Ders yoksa: Yeni bir `Lesson` modeli oluşturup kaydeder.
5.  **Raporlama**: İşlem sonunda kaç dersin eklendiğini, kaçının güncellendiğini ve oluşan hataları özet dizi olarak döndürür.
