[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **ErrorHandler**

---
# ErrorHandler

`ErrorHandler` sınıfı, uygulamadaki PHP hatalarını, istisnaları (exceptions) ve beklenmedik sistem duruşlarını yakalayıp kullanıcıya düzenli bir hata sayfası sunmak veya JSON yanıtı dönmekten sorumludur.

## Temel Görevi
PHP'nin standart hata yönetimini devralarak tüm hataları `Log` sistemi üzerinden kayıt altına alır ve kullanıcı deneyimini bozmadan uygun hata görünümlerini render eder.

## Metodlar
*   [register()](./register.md): PHP hata ve istisna işleyicilerini kaydeder.
*   [handleError()](./handleError.md): PHP hatalarını `ErrorException` nesnesine dönüştürür.
*   [handleException()](./handleException.md): Tüm istisnaları yakalayıp loglar ve uygun görünümü seçer.
*   [handleShutdown()](./handleShutdown.md): Ölümcül hataları yakalamak için script bitişinde çalışır.
*   [logException()](./logException.md): İstisnayı yapılandırılmış formatta loglar.
*   [renderErrorView()](./renderErrorView.md): HTTP durum koduna göre uygun HTML hata sayfasını gösterir.
*   [renderJsonError()](./renderJsonError.md): API istekleri için JSON formatında hata yanıtı döner.
*   [isAjax()](./isAjax.md): İsteğin AJAX (XMLHttpRequest) olup olmadığını kontrol eder.
