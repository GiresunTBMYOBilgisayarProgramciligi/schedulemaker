[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Helpers](../README.md) / [FilterValidator](./README.md) / **validateType**

---
# FilterValidator::validateType(string $key, $value)

Bir değerin master şemada belirtilen türle (veya çoklu türlerle) eşleşip eşleşmediğini kontrol eder.

## Mantık (Algoritma)
1.  **Şema Sorgusu**: İlgili anahtarın (`$key`) master şemada bir tür tanımı olup olmadığına bakar.
2.  **Çoklu Tip Ayrıştırma**: Tanımlı tipleri `|` karakterine göre ayırır (örn: `int|int[]`).
3.  **Tip Kontrolleri**:
    - `int`: `isIntegerish()` ile kontrol eder.
    - `string`: `is_string()` ile kontrol eder.
    - `array`: `is_array()` ile kontrol eder.
    - `int[]`: Dizinin tüm elemanlarının sayısal olup olmadığını `isArrayOf` ile kontrol eder.
4.  **Hata**: Eğer değer hiçbir tipe uymuyorsa, beklenen ve gelen tipleri belirterek hata fırlatır.
