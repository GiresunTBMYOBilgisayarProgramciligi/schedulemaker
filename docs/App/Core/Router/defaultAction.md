[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Router](./README.md) / **defaultAction**

---
# Router::defaultAction(string $action, array $params = [])

Belirli bir metod bulunamadığında otomatik olarak devreye giren fallback (yedek) mekanizmasıdır.

## Mantık (Algoritma)
1.  **Klasör Tespiti**: Çalışan Router'ın sınıf adından (örn: `AdminRouter`) `admin` klasör adını türetir.
2.  **Sayfa Tespiti**: Çağrılmak istenen eylem adından (örn: `settingsAction`) `settings` sayfa adını türetir.
3.  **Parametre Kontrolü**: Eğer URL'den bir parametre gelmişse, bu parametreyi bir dosya adı (view file) olarak kabul eder.
4.  **Dinamik View Oluşturma**: `viewPath` olarak `folder/page/file` (örn: `admin/settings/edit`) hiyerarşisini kurar.
5.  **Render**: `callView()` metodunu çağırarak ilgili dosyayı ekrana basmaya çalışır. Bu sayede her dosya için ayrı bir metod yazmaya gerek kalmadan "dosya üzerinden yönlendirme" sağlanır.
