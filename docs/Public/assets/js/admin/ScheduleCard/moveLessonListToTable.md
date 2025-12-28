[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **moveLessonListToTable**

---
# ScheduleCard.moveLessonListToTable(targetCell, lessonData)

Bir dersi yan listeden tutup tabloya bıraktığınızda çalışır ve görsel yerleşimi hazırlar.

## Mantık (Algoritma)
1.  **Hücre Hazırlığı**: Hedef tablo hücresinden (`targetCell`) günü ve başlangıç saatini alır.
2.  **Süre Kontrolü**: Dersin kaç saat süreceğini (`hours`) belirler.
3.  **Hücre Birleştirme (Rowspan)**: Ders 1 saatten uzunsa, altındaki hücreleri tarar ve `rowspan` kullanarak dikeyde birleşik bir alan oluşturur.
4.  **İçerik Ekleme**: Birleştirilen hücre içerisine dersin adını, kodunu ve hocasını içeren `schedule-item` HTML bloklarını yerleştirir.
    *   **Seçim Sıfırlama**: Yeni oluşturulan (klonlanan) kartın üzerindeki seçim sınıfı (`selected-lesson`) ve onay kutusu (`checkbox`) temizlenerek taze bir kart oluşturulması sağlanır.
5.  **Veri Kaydı**: Görsel yerleşim başarılı olduktan sonra `saveScheduleItems()` metodunu çağırarak veritabanı kaydını başlatır.
