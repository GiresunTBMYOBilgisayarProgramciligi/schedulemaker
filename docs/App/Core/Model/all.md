[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **all**

---
# Model::all()

Belirlenen koşullara uyan tüm kayıtları veritabanından çeker ve nesne dizisi olarak döndürür.

## Mantık (Algoritma)
1.  **Sorgu Hazırlığı**: `buildQuery()` metodunu çağırarak mevcut `where`, `orderBy`, `limit` ve `offset` ayarlarından bir SQL cümlesi ve parametre dizisi oluşturur.
2.  **Veritabanı İsteği**: PDO `prepare` ve `execute` ile sorguyu çalıştırır, tüm sonuçları `FETCH_ASSOC` (ilişkili dizi) olarak alır.
3.  **İlişki Yükleme (Eager Loading)**: Eğer `with()` metoduyla ilişki tanımlanmışsa, her bir sonuç satırı için ilgili modelleri (`loadRelations`) otomatik olarak yükler.
4.  **Model Dönüşümü**:
    - Eğer sadece belirli alanlar (`select`) istendiyse: Ham dizi sonuçlarını döndürür.
    - Tüm alanlar (`*`) istendiyse: Her bir sonuç satırı için yeni bir Model nesnesi türetir, `fill()` ile verileri aktarır ve bir nesne dizisi (`Model[]`) olarak döndürür.
