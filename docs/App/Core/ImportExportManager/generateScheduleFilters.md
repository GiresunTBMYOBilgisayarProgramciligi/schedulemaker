[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **generateScheduleFilters**

---
# ImportExportManager::generateScheduleFilters($filters)

UI'dan gelen basit filtreleri, SQL sorgularında ve Excel başlıklarında kullanılacak detaylı bir yapıya dönüştürür.

## Mantık (Algoritma)
1.  **Ayrıştırma**: `$filters` içindeki `lesson_id`, `lecturer_id`, `classroom_id` gibi değerleri okur.
2.  **Veritabanı Sorgusu**: Seçilen ID'lere karşılık gelen isimleri (örn: "Algoritma Dersi", "Ahmet Yılmaz") ilgili modellerden çeker.
3.  **Başlık Oluşturma**: Eğer bir bölüm/program seçilmişse, bunu Excel sayfasının en üstüne yazılacak bir başlık metnine çevirir.
4.  **Sonuç**: Hem veritabanını sorgulamak için rafine edilmiş bir filtre dizisi, hem de çıktı dosyasında kullanılacak metinsel başlıklar kümesi döndürür.
