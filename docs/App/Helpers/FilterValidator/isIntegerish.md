[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Helpers](../README.md) / [FilterValidator](./README.md) / **isIntegerish**

---
# FilterValidator::isIntegerish($value)

Bir değerin tam sayı olup olmadığını veya "tam sayı gibi" davranan bir metin/float olup olmadığını denetler.

## Mantık (Algoritma)
1.  **Doğrudan Kontrol**: PHP'nin `is_int()` fonksiyonu ile doğrudan tam sayı olup olmadığına bakar.
2.  **Gevşek Kontrol**: Değer sayısal (`is_numeric`) ise, tam sayıya çevrilmiş haliyle (`(int)`) kendisi birbirine eşitse `true` döner (örn: `"123"` veya `123.0` tam sayı kabul edilir).
