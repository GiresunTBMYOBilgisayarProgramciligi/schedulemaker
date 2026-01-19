[🏠 Ana Sayfa](../../../README.md) / [Public](../../README.md) / [assets](../README.md) / [js](./README.md) / **data_table.js**

---

# data_table.js

Uygulama genelinde kullanılan DataTables tablolarını özelleştirmek ve ek özellikler (filtreleme, popover vb.) eklemek için kullanılır.

## Özellikler

### 1. Varsayılan Yapılandırma
Tablolar `.dataTable` sınıfı ile seçilir ve aşağıdaki varsayılan ayarlar uygulanır:
- **Satır Sayısı:** Varsayılan olarak her sayfada 25 satır gösterilir (`pageLength: 25`).
- **Dil:** Türkçe dil desteği `/assets/js/datatable_tr.json` dosyasından yüklenir.

### 2. Sütun Bazlı Filtreleme
Başlık (th) elementinde `filterable` sınıfı bulunan sütunlar için otomatik olarak dropdown filtre listesi oluşturulur.
- **HTML Temizleme:** Filtre listesindeki seçenekler, hücre içeriğindeki HTML etiketlerinden temizlenerek gösterilir.
- **Regex Arama:** Filtreleme işlemi tam eşleşme sağlayacak şekilde Regex kullanılarak yapılır.
- **İkon Durumu:** Bir sütunda aktif filtre varsa filtre ikonu (`bi-funnel-fill`) dolgulu hale gelir.

### 3. Popover Desteği
Sayfa yüklendiğinde `data-bs-toggle="popover"` özniteliğine sahip tüm elementler için Bootstrap Popover özelliği etkinleştirilir. Bu özellik genellikle bağlı derslerin bilgilerini göstermek için kullanılır.

## Kullanım Notları

- Filtre dropdown'u açıldığında tablonun sıralama (sorting) özelliğinin tetiklenmesi engellenmiştir.
- Filtre seçenekleri sütundaki benzersiz (unique) verilerden otomatik olarak derlenir.
