[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **is_data_serialized**

---
# Model::is_data_serialized($data)

Verilen metnin PHP `serialize()` formatında olup olmadığını kontrol eder.

## Mantık (Algoritma)
1.  **Tip Kontrolü**: Veri string değilse direkt `false` döner.
2.  **Format Kontrolü**: Stringin başındaki karakterlere bakarak (a:, s:, i:, d:, b:, O:, C:) standart serileştirme işaretlerini arar.
3.  **Validasyon**: `unserialize()` fonksiyonu ile veriyi açmayı dener. Eğer hata oluşursa veya geri dönüş açılış formatıyla tutarsızsa hatayı bastırır ve `false` döner.
4.  **Sonuç**: Veri geçerli bir PHP serileştirmesi ise `true` döner.
