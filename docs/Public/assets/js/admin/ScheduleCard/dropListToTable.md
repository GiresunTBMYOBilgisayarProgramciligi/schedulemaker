[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **dropListToTable**

---
# ScheduleCard.dropListToTable()

Bir dersi yan taraftaki "Müsait Dersler" listesinden tutup tabloya bıraktığınızda çalışan ana koordinasyon metodudur.

## Mantık (Algoritma)
1.  **Ders Türü Kontrolü**:
    - Eğer `owner_type` bir sınıf (`classroom`) değilse:
        - Sınav programıysa gözetmen ve derslik seçimi modalını açar.
        - Ders programıysa derslik ve saat seçimi modalını açar.
    - Eğer `owner_type` zaten bir sınıfsa, sadece saat seçimi modalını açar.
2.  **Ön Kontrol (Frontend)**: `checkCrash()` metodunu çağırarak seçilen saatlerin tabloda boş olup olmadığını denetler.
3.  **Veri Hazırlığı**: `generateScheduleItems()` ile kaydedilecek veri paketini oluşturur.
4.  **Backend Kontrolü**: `checkCrashBackEnd()` ile sunucu tarafındaki (hoca meşguliyeti vb.) kısıtları sorgular.
5.  **Kaydetme**: Hiçbir çakışma yoksa `saveScheduleItems()` ile veritabanına kaydeder.
6.  **Görselleştirme**: Kayıt başarılıysa `moveLessonListToTable()` ile dersi tabloya kalıcı olarak yerleştirir.
7.  **Sıfırlama**: İşlem sonunda `resetDraggedLesson()` ile sürükleme verilerini temizler.
