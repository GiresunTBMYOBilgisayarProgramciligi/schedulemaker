[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ErrorHandler](./README.md) / **renderJsonError**

---
# ErrorHandler::renderJsonError(Throwable $exception, int $statusCode)

API veya AJAX istekleri için yapılandırılmış JSON hata yanıtı oluşturur.

## Mantık (Algoritma)
1.  **Temizlik**: `ob_end_clean` ile önceden oluşmuş olabilecek tampon çıktıları siler.
2.  **HTTP Status**: `http_response_code` ile tarayıcıya/istemciye durum kodunu gönderir.
3.  **Başlık**: `Content-Type: application/json` başlığını ekleyerek yanıtın JSON olduğunu bildirir.
4.  **Payload**:
    - `success: false`
    - `message`: İstisna mesajı.
    - `code`: İstisna kodu.
    - `debug`: Eğer `DEBUG` modu açıksa hata dosyası, satırı ve trace bilgisini de ekler.
5.  **Output**: `json_encode` ile diziyi metne çevirip ekrana basar ve scripti durdurur.
