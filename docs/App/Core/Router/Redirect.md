[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Router](./README.md) / **Redirect**

---
# Router::Redirect(?string $path, bool $goBack = true)

Kullanıcıyı güvenli bir şekilde başka bir URL'ye yönlendirir.

## Mantık (Algoritma)
1.  **Varsayılan Yol**: Eğer `$path` belirtilmemişse, hedefi `/admin` olarak ayarlar.
2.  **Geri Dönüş Kontrolü**: `$goBack` parametresi `true` ise:
    - Tarayıcının gönderdiği `HTTP_REFERER` (önceki sayfa) bilgisini kontrol eder.
    - Varsa kullanıcıyı geldiği sayfaya, yoksa belirlenen yola yönlendirir.
3.  **Doğrudan Yönlendirme**: `$goBack` `false` ise doğrudan hedef yola yönlendirme yapar.
4.  **PHP Header**: `header("location: ...")` komutuyla yönlendirmeyi başlatır ve `exit()` ile scriptin devam etmesini durdurur.
