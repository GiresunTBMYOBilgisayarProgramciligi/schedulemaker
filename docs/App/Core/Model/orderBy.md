[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **orderBy**

---
# Model::orderBy(string $column, string $direction = 'ASC')

Sonuçların hangi sütuna ve hangi yöne göre sıralanacağını belirler.

## Mantık (Algoritma)
1.  **Validasyon**: `$direction` değerinin 'ASC' veya 'DESC' olup olmadığını kontrol eder (veya varsayılanı kullanır).
2.  **Kayıt**: `$column $direction` formatındaki metni dahili `order_by` listesine ekler.
3.  **Zincirleme**: `$this` döner.
