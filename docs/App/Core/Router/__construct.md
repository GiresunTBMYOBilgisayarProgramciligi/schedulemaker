[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Router](./README.md) / **__construct**

---
# Router::__construct()

Yönlendirici (Router) nesnesini başlatır ve görünüm (view) katmanı için temel varlıkları hazırlar.

## Mantık (Algoritma)
1.  **Veri Temizliği**: `$view_data` dizisini boş bir dizi olarak ilklendirir.
2.  **Asset Yönetimi**: Yeni bir `AssetManager` örneği oluşturur.
3.  **Global Paylaşım**: Oluşturulan `AssetManager` nesnesini, tüm görünümlerde kullanılabilmesi için `$view_data["assetManager"]` anahtarına atar.
