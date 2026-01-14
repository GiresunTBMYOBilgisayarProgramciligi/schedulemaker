[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [AssetManager](./README.md) / **renderJs**

---
# AssetManager::renderJs()

Kayıtlı tüm JavaScript dosyalarını HTML formatında çıktıya dönüştürür.

## Mantık (Algoritma)
1.  **Döngü Başlangıcı**: `$js` dizisindeki her bir eleman için süreç başlatılır.
2.  **Öznitelik Hazırlığı**: Her dosya için varsayılan olarak `src` (dosya yolu) niteliği hazırlanır.
3.  **Ek Nitelikler**: Eğer dosyaya ait özel öznitelikler (`async`, `defer` vb.) varsa, bunlar `key="value"` formatında HTML dizinine eklenir.
    - *Güvenlik*: Öznitelik değerleri `htmlspecialchars()` ile sanitize edilir.
4.  **Etiket Oluşturma**: Hazırlanan nitelikler `<script></script>` etiketleri arasına yerleştirilir.
5.  **Bitiş**: Tüm etiketler birleştirilerek tek bir HTML metni olarak döndürülür.
