[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **getArray**

---
# Model::getArray(array $excludedProperties = [], bool $acceptNull = false)

Model nesnesini, veritabanı işlemleri veya API çıktıları için saf bir PHP dizisine dönüştürür.

## Mantık (Algoritma)
1.  **Yansıma (Reflection)**: Sınıfın tüm public özelliklerini (`properties`) tarar.
2.  **Filtreleme**:
    - `$excludedProperties` dizisindeki yasaklı alanları (örn: `password`) eler.
    - `$acceptNull` false ise, değeri `null` olan özellikleri diziye dahil etmez.
3.  **Dönüş**: Modeldeki verilerin anahtar-değer (key-value) formatındaki dizisini döndürür.
