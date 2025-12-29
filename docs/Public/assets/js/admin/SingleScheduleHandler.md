[🏠 Ana Sayfa](../../../../README.md) / [Public](../../../README.md) / [assets](../../README.md) / [js](../README.md) / [admin](./README.md) / **SingleScheduleHandler.js**

---

# SingleScheduleHandler.js

`SingleScheduleHandler`, tekli program sayfalarında (Hoca, Ders, Derslik) "Tercih Edilen" (Preferred) ve "Uygun Olmayan" (Unavailable) zaman dilimlerini yönetmek için kullanılan bir JavaScript sınıfıdır.

`ScheduleCard.js` dosyasının basitleştirilmiş ve modernize edilmiş bir versiyonudur. Sürükle-bırak (drag-and-drop), toplu seçim (bulk selection) ve modern bildirim (Modal/Toast) özellikleri ile özel statüdeki öğelerin yönetimini sağlar.

## Temel Özellikler

-   **Sürükle-Bırak Desteği**: `lesson-card`, `slot-preferred` ve `slot-unavailable` sınıflarına sahip öğelerin sürüklenmesini sağlar.
-   **Toplu Seçim (Bulk Selection)**:
    -   **Tek Tık**: Slot seçimi ve checkbox işaretleme.
    -   **Çift Tık**: Aynı gün içindeki aynı statüye sahip tüm öğelerin seçilmesi.
-   **Canlı Güncelleme (Live Update)**: İşlemlerden (silme, taşıma, parçalama) sonra sayfa yenilenmeden, etkilenen hücreler backend'den gelen verilere göre anlık olarak güncellenir.
-   **Tablo İçi Taşıma (Table-to-Table Move)**:
    - **Toplu Taşıma**: Seçili birden fazla öğenin aynı anda taşınması desteği.
    - **Zaman Koruma**: Taşınan öğelerin orijinal süreleri ve statüleri hedef konumda korunur.
-   **Modern Bildirimler**: Klasik `alert`/`confirm` yerine Bootstrap Modal ve Toast kullanımı.
-   **Gelişmiş Silme ve Parçalama (Split)**:
    -   **Toplu Silme**: Seçili tüm öğelerin tek seferde silinmesi.
    -   **Parçalı Silme**: 8 saatlik bir bloğun içinden sadece seçilen saatlerin silinip geri kalanların korunması (Split) backend tarafında tam desteklenir.
    -   **Dummy Öğe Koruması**: Verisi boş olan özel slotlar (Preferred/Unavailable) parçalanırken korunur ve veri kaybı önlenir.
    -   **Öğle Arası Uyumu**: Silme/Parçalama sırasında 12:00-13:00 aralığı otomatik olarak hesaplanır ve slot kaymaları engellenir.
-   **Dinamik Süre**: Slot süresi ve teneffüs değerlerini doğrudan kart verilerinden okur.

## Kullanım

Sayfa yüklendiğinde otomatik olarak `DOMContentLoaded` olayında başlatılır:

```javascript
document.addEventListener('DOMContentLoaded', () => {
    window.singleScheduleHandler = new SingleScheduleHandler();
});
```

## Önemli Metodlar

-   `initDraggableItems()`: Sürüklenebilir öğeleri hazırlar.
-   `initBulkSelection()`: Tıklama ve çift tıklama ile toplu seçim mantığını kurar.
-   `initModals()`: Ekleme/Güncelleme ve Silme Onayı için Bootstrap modallarını hazırlar.
-   `syncTableItems(items)`: Backend'den gelen yeni/güncellenmiş öğeleri tabloya yansıtır.
-   `clearTableItemsByIds(ids)`: Silinen öğeleri tablodan temizler ve hücreleri boşaltır.
-   `handleTableMove()`: Seçili veya sürüklenen öğelerin yeni bir hücreye taşınmasını yönetir (Eskileri siler, yenileri ekler).
-   `handleDeleteDrop()`: Seçili veya sürüklenen öğeleri silme onay modalı ile siler.
-   `saveItem(items)`: AJAX ile verileri kaydeder ve `Toast` ile geri bildirim verir.
