[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **Controller**

---
# Controller

`Controller` sınıfı, tüm uygulama kontrolcülerinin (App\Controllers) türetildiği temel (base) sınıftır.

## Temel Görevi
Veritabanı bağlantısına erişim, loglama asistanları ve yaygın kullanılan veri listeleme/sayma işlemlerini standartlaştırarak alt sınıfların daha az kodla daha çok iş yapmasını sağlar.

## Metodlar
*   [__construct()](./__construct.md): Veritabanı bağlantısını (`database`) ilklendirir.
*   [getCount()](./getCount.md): Belirli filtrelere uyan toplam kayıt sayısını döner.
*   [getListByFilters()](./getListByFilters.md): Filtrelere göre model nesnelerinden oluşan bir liste döner.
*   [logger()](./logger.md): Monolog logger örneğine erişim sağlar.
*   [logContext()](./logContext.md): Kontrolcü işlemleri için standart log bağlamı üretir.
