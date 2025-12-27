[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **getDatasetValue**

---
# ScheduleCard.getDatasetValue(setObject, getObject)

HTML elementinin dataset (`data-*`) özniteliklerini otomatik olarak bir objeye snake_case formatında kopyalar.

## Mantık (Algoritma)
1.  **Dahili Fonksiyon (toSnakeCase)**: CamelCase olan dataset anahtarlarını (örn: `lessonId`) snake_case formatına (örn: `lesson_id`) çevirir.
2.  **İç İçe Döngü**:
    - `setObject` (hedef) içindeki her anahtar için `getObject.dataset` (kaynak) içindeki tüm anahtarları gezer.
    - Eğer kaynak anahtarın snake_case hali hedef anahtarla eşleşirse, değeri aktarır.
3.  **Amaç**: Manuel `dataset.xxx` atamalarını azaltarak dinamik veri transferi sağlar.
