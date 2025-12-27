[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **sum**

---
# Model::sum(string $column)

Belirli bir sayısal sütunun (örn: kredi sayısı, ücret) toplamını hesaplar.

## Mantık (Algoritma)
1.  **SELECT İnşası**: `SELECT SUM(column) as total` metnini hazırlar.
2.  **WHERE**: Daha önceden tanımlanmış filtreleri (`where()`, `get()`) sorguya ekler.
3.  **Yürütme**: Ham SQL'i çalıştırır ve dönen toplama (total) değerini bir sayı (integer/float) dökümü olarak döndürür.
