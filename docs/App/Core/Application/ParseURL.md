[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Application](./README.md) / **ParseURL**

---
# Application::ParseURL()

Gelen `REQUEST_URI` bilgisini analiz ederek uygulamanın geri kalanında kullanılacak yönlendirme bilgilerini oluşturur.

## Mantık
- `$_SERVER["REQUEST_URI"]` bilgisini alır ve uçlardaki `/` işaretlerini temizler.
- Slash (`/`) karakterine göre dizilere böler.
- İlk parça `Router` adını (varsayılan: `HomeRouter`), ikinci parça `Action` adını (varsayılan: `IndexAction`) temsil eder.
- Geri kalan tüm parçalar `parameters` dizisine aktarılır.
- Router adının sonuna otomatik olarak "Router", Action adının sonuna ise "Action" eklenerek isimlendirme standartlaştırılır.
