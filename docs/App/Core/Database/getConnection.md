[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Database](./README.md) / **getConnection**

---
# Database::getConnection()

Veritabanı bağlantı motorudur. Singleton tasarım kalıbını kullanarak uygulama boyunca tek bir PDO örneği (`connection`) üzerinden işlem yapılmasını garanti eder.

## Teknik Detaylar
- `.env` dosyasındaki `DB_HOST`, `DB_NAME`, `DB_USER` ve `DB_PASS` bilgilerini kullanır.
- Bağlantı sırasında `utf8mb4` karakter setini set eder.
- Hata yönetimini `PDO::ERRMODE_EXCEPTION` olarak ayarlayarak veritabanı hatalarının yakalanabilir olmasını sağlar.
- Varsayılan fetch modunu `PDO::FETCH_ASSOC` (ilişkili dizi) olarak belirler.
- Eğer bağlantı bir kere kurulduysa, bellekteki (`static`) aynı nesneyi döndürür; değilse yeni bir bağlantı açar.
