[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **Model**

---
# App\Core\Model

`Model`, tüm veritabanı modellerinin (User, Lesson vb.) türetildiği temel sınıftır. Modern bir ORM (Object-Relational Mapping) yapısı sunarak SQL yazmadan CRUD işlemlerine imkan tanır.

## Temel İşlevler

1.  **Query Builder**: `where`, `orderBy`, `limit`, `offset` gibi fonksiyonlarla dinamik SQL oluşturma.
2.  **CRUD**: `create()`, `update()`, `delete()`, `find()`, `all()` metodları.
3.  **İlişki Yönetimi**: `belongsTo`, `hasMany` gibi temel ilişki yapılarını simüle eder.
4.  **Serileştirme**: Verilerin JSON formatına otomatik dönüştürülmesi.

## Metod Listesi

### Sorgu Oluşturucu (Query Builder)
*   [get()](./get.md): Query builder'ı başlatır.
*   [select()](./select.md): Seçilecek alanları belirler.
*   [where()](./where.md): Dinamik SQL WHERE koşulları oluşturur.
*   [orderBy()](./orderBy.md): Sıralama kriteri ekler.
*   [limit()](./limit.md): Sorgu sonucuna limit koyar.
*   [offset()](./offset.md): Sorgu sonucuna başlangıç kaydı (offset) koyar.
*   [with()](./with.md): İlişkili modellerin (Eager Loading) yüklenmesini sağlar.

### Veri İşlemleri (CRUD & Data)
*   [create()](./create.md): Yeni bir kayıt oluşturur.
*   [update()](./update.md): Mevcut kaydı günceller.
*   [delete()](./delete.md): Kaydı veritabanından siler.
*   [all()](./all.md): Koşullara uyan tüm kayıtları döner.
*   [first()](./first.md): Koşullara uyan ilk kaydı döner.
*   [find()](./find.md): ID üzerinden tekil kayıt bulur.
*   [fill()](./fill.md): Dizi verisini model özelliklerine aktarır.

### Yardımcı ve Dahili Metodlar
*   [__construct()](./__construct.md): Veritabanı bağlantısını ilklendirir.
*   [logger()](./logger.md): Model bazlı loglama nesnesine erişir.
*   [logContext()](./logContext.md): Model işlemleri için log bağlamı hazırlar.
*   [buildQuery()](./buildQuery.md): SQL metnini ve parametreleri inşa eder.
*   [loadRelations()](./loadRelations.md): Tanımlı ilişkileri sonuç kümesine yükler.
*   [count()](./count.md): Kayıt sayısını döner.
*   [sum()](./sum.md): Belirli bir sütunun toplamını döner.
*   [is_data_serialized()](./is_data_serialized.md): Verinin seri halde olup olmadığını kontrol eder.
*   [getArray()](./getArray.md): Model verilerini dizi olarak döner.
*   [getLabel()](./getLabel.md): Modelin Türkçe etiket adını döner.
*   [getLogDetail()](./getLogDetail.md): Loglarda gösterilecek nesne detayını döner.
*   [getDepartmentProgramsList()](./getDepartmentProgramsList.md): Bölüme bağlı program listesini hazırlar.
