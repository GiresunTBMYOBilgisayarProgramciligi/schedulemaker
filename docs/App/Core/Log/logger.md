[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Log](./README.md) / **logger**

---
# Log::logger()

Uygulama genelinde kullanılacak Monolog nesnesini hazırlar ve döndürür (Static Singleton).

## Mantık (Algoritma)
1.  **Önbellek Kontrolü**: Eğer daha önce bir logger nesnesi oluşturulmuşsa, doğrudan o nesneyi döndürür.
2.  **Kanal Oluşturma**: 'app' kanal isminde yeni bir Monolog `Logger` nesnesi türetir.
3.  **DbLogHandler**: Her durumda veritabanına log yazmak için `DbLogHandler`'ı handler listesine ekler.
    - Hata seviyesi: DEBUG moduna göre belirlenir.
4.  **Debug Modu Kontrolü**: Eğer `.env` dosyasında `DEBUG=true` ise:
    - `debug.log`, `info.log` ve `error.log` dosyalarına yazmak için `StreamHandler` ve `FilterHandler` eklemelerini yapar.
5.  **Dönüş**: Yapılandırılmış logger nesnesini static değişkene kaydederek döndürür.
