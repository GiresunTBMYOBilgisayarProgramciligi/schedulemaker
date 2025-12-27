# Mimari Genel Bakış

Schedule Maker projesi, modüler ve genişletilebilir bir yapı sağlamak amacıyla özel olarak geliştirilmiş bir **MVC (Model-View-Controller)** çatısı üzerine inşa edilmiştir.

## Temel Bileşenler (Core)

Sistemin çekirdeği `App/Core` dizini altında bulunur ve uygulamanın yaşam döngüsünü yöneten temel sınıfları içerir.

### 1. Router (`App/Core/Router.php`)
Uygulamaya gelen tüm HTTP isteklerinin ilk karşılandığı noktadır. URL yapısını analiz eder ve ilgili Controller sınıfını ve metodunu (Action) tetikler.

*   **View Data Yönetimi**: `$view_data` özelliği ile View katmanına aktarılacak verileri toplar.
*   **Asset Manager**: `AssetManager` sınıfını yükleyerek CSS/JS dosyalarının yönetimini sağlar.
*   **callView()**: Belirtilen view dosyasını render eder (örn: `admin/pages/schedule`).
*   **Default Action**: Eğer özel bir metod adı belirtilmemişse veya bulunamazsa, URL parametrelerine göre dinamik olarak bir view dosyası bulmaya çalışır (`defaultAction`).

### 2. Controller (`App/Core/Controller.php`)
Tüm Controller sınıflarının (örn: `ScheduleController`, `UserController`) türediği ana sınıftır.

*   **Veritabanı Bağlantısı**: `__construct` metodunda `Database::getConnection()` ile PDO bağlantısını başlatır ve `$this->database` özelliğine atar.
*   **Logger**: `logger()` ve `logContext()` metodları ile merkezi loglama altyapısı sağlar.
*   **Yardımcı Metodlar**: `getCount` ve `getListByFilters` gibi sık kullanılan veritabanı işlemlerini standartlaştırır.

### 3. Model (`App/Core/Model.php`)
Veritabanı tablolarının nesne tabanlı karşılığıdır (ORM benzeri bir yapı sunar).

*   **Query Builder**: `select()`, `where()`, `orderBy()`, `limit()`, `with()` (ilişki yükleme) gibi zincirleme metodlarla SQL sorguları oluşturmayı sağlar.
*   **CRUD İşlemleri**: `create()`, `update()`, `delete()`, `find()` metodları ile temel veritabanı işlemlerini otomatikleştirir.
*   **İlişki Yönetimi**: `loadRelations()` metodu ile tanımlı ilişkileri (örn: bir dersin hocası) otomatik olarak yükleyebilir.

---

## İstek Yaşam Döngüsü (Request Lifecycle)

Bir kullanıcı `/ajax/saveScheduleItem` adresine istek attığında süreç şöyle işler:

1.  **Giriş**: İstek `index.php` (veya `.htaccess` yönlendirmesi) üzerinden uygulamaya girer.
2.  **Routing**: `App/Application.php` (veya ana giriş noktası), URL'i analiz eder ve `AjaxRouter` sınıfını başlatır.
3.  **Action**: `AjaxRouter`, URL'deki action adına göre `saveScheduleItemAction` metodunu çağırır.
4.  **Controller**: Router, `ScheduleController` sınıfını başlatır ve `saveScheduleItems` metoduna veriyi gönderir.
5.  **Logic & DB**: Controller, iş mantığını çalıştırır (çakışma kontrolü vb.) ve `Model` sınıflarını kullanarak veritabanına yazar.
6.  **Response**: Sonuç JSON formatında Router üzerinden istemciye geri döner.

Bu yapı, iş mantığı (Controller), veri erişimi (Model) ve sunum katmanını (View/Router) birbirinden izole ederek kodun yönetilebilirliğini artırır.
