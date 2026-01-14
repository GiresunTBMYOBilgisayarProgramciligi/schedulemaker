[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **Router**

---
# App\Core\Router

`Router`, URL'yi analiz ederek isteği ilgili `Controller` ve `Action`'a yönlendiren yönetim merkezidir.

## Temel İşleyiş

1.  **URL Parse**: `/controller/action` yapısındaki URL'yi ayrıştırır.
2.  **Dispatch**: Parametreleri hazırlayarak ilgili sınıfı ayağa kaldırır ve metodu çağırır.
3.  **View Rendering**: Controller tarafından iletilen verileri `View` sınıfı üzerinden HTML'e dönüştürür.

## Metod Listesi

*   [__construct()](./__construct.md): Router'ı ilklendirir ve AssetManager'ı hazırlar.
*   [callView()](./callView.md): Belirtilen view dosyasını verilerle birlikte render eder.
*   [Redirect()](./Redirect.md): Kullanıcıyı başka bir sayfaya veya bir önceki sayfaya yönlendirir.
*   [defaultAction()](./defaultAction.md): Belirli bir action bulunamadığında otomatik view eşleştirmesi yapar.
*   [logger()](./logger.md): Kontrolcüler için merkezi loglama arayüzü sağlar.
*   [logContext()](./logContext.md): Log mesajlarına kullanıcı ve sistem bilgisini otomatik ekler.
