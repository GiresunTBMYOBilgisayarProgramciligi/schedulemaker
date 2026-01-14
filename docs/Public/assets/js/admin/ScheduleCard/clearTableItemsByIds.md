[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **clearTableItemsByIds**

---
# ScheduleCard.clearTableItemsByIds(idsToRemove)

Verilen ID listesine sahip ders kartlarını görsel olarak tablodan siler ve hücreleri temizler.

## Mantık (Algoritma)
1.  Parametre olarak gelen ID dizisi içindeki her bir ID için:
    - Tabloda `data-id` değeri bu ID'ye eşit olan ders kartını bulur.
    - Kartın bulunduğu hücrenin dikey birleşimini (`rowspan`) bozarak hücreyi eski tekli haline getirir.
    - Kartı DOM'dan tamamen kaldırır.
2.  Sadece görsel temizlik yapar; PHP tarafındaki silme işleminin sonucuna göre tetiklenir.
