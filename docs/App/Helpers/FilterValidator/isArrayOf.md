[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Helpers](../README.md) / [FilterValidator](./README.md) / **isArrayOf**

---
# FilterValidator::isArrayOf(array $array, $checkFunction)

Bir dizinin içindeki her bir elemanın belirli bir kriteri karşılayıp karşılamadığını denetler.

## Mantık (Algoritma)
1.  **Boş Dizi**: Eğer dizi boşsa, kuralı ihlal etmediği varsayılarak direkt `true` döner.
2.  **Fonksiyon Hazırlığı**: Eğer `$checkFunction` bir string olarak 'isIntegerish' geldiyse, bunu sınıf içindeki metodla (`[$this, 'isIntegerish']`) eşleştirir.
3.  **Döngü**: Dizi elemanlarını tek tek iterate eder ve belirtilen kontrol fonksiyonundan geçirir.
4.  **Sonuç**: Eğer tek bir eleman bile kontrolden geçemezse `false`, tümü geçerse `true` döner.
