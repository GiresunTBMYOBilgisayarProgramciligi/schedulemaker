[🏠 Ana Sayfa](../../../README.md) / [Public](../../README.md) / [assets](../README.md) / [js](README.md) / **ajax**

---
# Public\assets\js\ajax.js

Proje genelinde kullanılan merkezi AJAX yönetim scriptidir.

## Temel İşlevler

1.  **Merkezi İstek Yönetimi**: Tüm asenkron istekler buradaki `ajaxRequest` fonksiyonu üzerinden geçer.
2.  **Hata Yakalama**: Gelen HTTP status kodlarına göre (401, 403, 500) global hata mesajları gösterir.
3.  **Loading Spinner**: İstek başladığında bir yükleme ikonu gösterir, bittiğinde gizler.
4.  **CSRF/Güvenlik**: İsteklere otomatik olarak gerekli güvenlik headerlarını ekler.

## Metodlar
*   **ajaxRequest(url, data, successCallback, errorCallback)**: Temel AJAX sarmalayıcısı.
*   **fetchForm(form, data)**: Formları AJAX ile gönderir. `data-toast="true"` özniteliği ile modal yerine toast bildirimlerini ve gecikmeli yönlendirmeleri destekler.
