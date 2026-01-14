[🏠 Ana Sayfa](../../README.md) / [App](../README.md) / [Routers](README.md) / **SpecificRouters**

---
# App\Routers\SpecificRouters

Uygulamanın mantıksal bölümlerine göre özelleşmiş router sınıflarıdır.

## AdminRouter
Yönetim panelindeki `classrooms`, `lessons`, `users` gibi sayfaların render edilmesinden ve standart GET/POST işlemlerinden sorumludur. Giriş yapılmamışsa gidilmek istenen URL'yi hafızaya alarak kullanıcıyı login sayfasına yönlendirir.

## AjaxRouter
Sürükle-bırak takvimi, dinamik filtreleme ve hızlı kayıt işlemlerinde kullanılan JSON dönen uç noktalardır.
*   *Kritik:* `saveScheduleItemAction`, `deleteScheduleItemsAction`.

## AuthRouter
Giriş (`Login`), Çıkış (`Logout`) ve Kayıt (`Register`) süreçlerini yönetir. Giriş sonrası hafızadaki (varsa) önceki sayfaya, yoksa `/admin` sayfasına yönlendirme yapar.

## HomeRouter
Giriş yapmamış kullanıcıların veya öğrencilerin göreceği genel ders programı sayfalarını yönetir.
