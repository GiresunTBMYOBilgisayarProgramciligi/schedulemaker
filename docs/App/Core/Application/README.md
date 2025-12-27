[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **Application**

---
# App\Core\Application

`Application` sınıfı, uygulamanın giriş noktasıdır (Entry Point). Tüm servislerin (Database, Router, Log) ayağa kaldırılmasından ve Request-Response döngüsünün başlatılmasından sorumludur.

## Temel İşleyiş ve İstek Yaşam Döngüsü

Bir kullanıcı `/ajax/saveScheduleItem` adresine istek attığında süreç şu katmanlardan geçer:

1.  **Giriş**: İstek `index.php` üzerinden uygulamaya girer. `Application` sınıfı başlatılır.
2.  **İlklendirme**: `.env` dosyası ve yapılandırmalar yüklenir. Veritabanı bağlantısı (`Database`) ve `AssetManager` hazır hale getirilir.
3.  **Routing**: URL, `Router` (veya `AjaxRouter`) tarafında analiz edilir.
4.  **Controller & Action**: Router, `ScheduleController` sınıfını başlatır ve `saveScheduleItems` metoduna veriyi gönderir.
5.  **Logic & DB**: Controller iş mantığını (çakışma kontrolü vb.) çalıştırır ve `Model` sınıfları üzerinden veritabanına yazar.
6.  **Response**: Sonuç JSON/HTML formatında istemciye geri döner.

## Metod Listesi

*   [__construct()](./__construct.md): Uygulamayı ilklendirir, URL'yi parse eder ve uygun kontrolcüyü başlatır.
*   [ParseURL()](./ParseURL.md): Gelen isteği parçalara ayırarak Router, Action ve Parametreleri belirler.
