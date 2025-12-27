[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Application](./README.md) / **__construct**

---
# Application::__construct()

Uygulamanın başlangıç metodudur. Nesne örneği oluşturulduğunda otomatik olarak çalışır.

## İşleyiş Adımları
1.  `ParseURL()` metodunu çağırarak URL bileşenlerini ayıklatır.
2.  Belirlenen Router ismini `App\Routers` namespace'i ile birleştirir.
3.  İlgili Router sınıfını `new` anahtar kelimesiyle ayağa kaldırır.
4.  Router içerisinde talebi karşılayacak `Action` metodunun varlığını kontrol eder (`method_exists`).
5.  Eğer metod varsa `call_user_func_array` ile parametreleri göndererek çalıştırır.
6.  Metod yoksa Router'ın `defaultAction` metodunu fallback olarak devreye sokar.
