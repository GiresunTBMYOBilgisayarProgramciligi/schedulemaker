[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Log](./README.md) / **logger**

---
# Log::logger()

Uygulama genelinde kullanılacak Monolog nesnesini hazırlar ve döndürür (Static Singleton).

## Mantık (Algoritma)
1.  **Önbellek Kontrolü**: Eğer daha önce bir logger nesnesi oluşturulmuşsa, doğrudan o nesneyi döndürür.
2.  **Kanal Oluşturma**: 'app' kanal isminde Monolog `Logger` nesnesi türetir.
3.  **AppRotatingFileHandler**: `Logs/{channel}-{date}.log` dosyalarına JSON formatında rotasyonlu olarak log yazar.
    - Saklama süresi ve dosya rotasyon periyodu sistem ayarlarından (`settings`) dinamik okunur.
4.  **Dönüş**: Yapılandırılmış logger nesnesini static değişkene kaydederek döndürür.
