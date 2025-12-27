[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **fill**

---
# Model::fill(array $data)

Dışarıdan gelen bir dizideki verileri modelin özelliklerine (properties) güvenli ve akıllı bir şekilde aktarır.

## Mantık (Algoritma)
1.  **Yansıma (Reflection)**: `ReflectionClass` kullanarak alt sınıfın tüm `public` özelliklerini tespit eder.
2.  **Döngü**: Tespit edilen her bir özellik için `$data` dizisinde karşılık gelen bir anahtar olup olmadığına bakılır.
3.  **Tarih Kontrolü**: Özellik adı `dateFields` listesinde kayıtlıysa, gelen string değeri bir PHP `DateTime` nesnesine dönüştürülerek atanır.
4.  **Seri Veri Kontrolü**: Veri string ise, `is_data_serialized` metoduyla PHP'nin `serialize` formatında olup olmadığına bakılır. Eğer öyleyse otomatik olarak `unserialize()` edilir.
5.  **Atama**: İşlenmiş veri, modelin ilgili özelliğine atanır.
