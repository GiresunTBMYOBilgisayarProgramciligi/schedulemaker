[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ErrorHandler](./README.md) / **handleException**

---
# ErrorHandler::handleException($exception)

Fırlatılan tüm istisnaların merkezi yönetim noktasıdır.

## Mantık (Algoritma)
1.  **Loglama**: Gelen istisna nesnesini `logException()` metoduna göndererek sistem loglarına (veritabanı veya dosya) kaydeder.
2.  **Çıktı Tamponu**: `ob_get_level` kontrolü ile açık olan tüm çıktı tamponlarını temizler (yarım kalmış HTML çıktılarını siler).
3.  **Yanıt Türü**: İsteğin bir AJAX (JSON) isteği olup olmadığını kontrol eder.
4.  **Render**: 
    - Eğer AJAX ise: `renderJsonError()` ile JSON formatında hata dönene kadar süreci yönetir.
    - Değilse: `renderErrorView()` ile kullanıcıya şık bir PHP/HTML hata sayfası (404, 500 vb.) sunar.
