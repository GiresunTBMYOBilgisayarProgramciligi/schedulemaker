[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ErrorHandler](./README.md) / **isAjax**

---
# ErrorHandler::isAjax()

Gelen HTTP isteğinin bir AJAX (asenkron JavaScript) veya API isteği olup olmadığını tespit eder.

## Mantık (Algoritma)
1.  **Header Kontrolü**: `$_SERVER['HTTP_X_REQUESTED_WITH']` başlığının `xmlhttprequest` olup olmadığını (küçük harfe çevirerek) kontrol eder.
2.  **Referer/Path Kontrolü**: (Tahmini proje mantığı) Eğer URL yapısı `/api/` ile başlıyorsa veya `Accept` başlığı JSON bekliyorsa true döner.
3.  **Sonuç**: Eğer koşullardan biri sağlanıyorsa `true`, sağlanmıyorsa `false` değeri döndürür. Bu değer, hatanın HTML olarak mı yoksa JSON olarak mı gösterileceğine karar vermek için kullanılır.
