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
-   **Dinamik Slot Parçalama (Unavailable Aware Splitting)**: 
    -   İşlemler sırasında hedef hücrelerin durumu (`.slot-unavailable`) kontrol edilir.
    -   Bir blok oluşturulurken veya taşınırken, aradaki "Unavailable" veya "Öğle Arası" olan hücreler otomatik olarak atlanır (skip).
    -   Blok bu engellere göre otomatik olarak parçalara (split) bölünür.
-   **Koşullu Açıklama Kaydı**: Gereksiz veritabanı doluluğunu önlemek için sadece içi dolu açıklamalar `detail` alanına kaydedilir; boş açıklamalar için `detail` alanı `null` olarak gönderilir.
-   **Dinamik Süre**: Slot süresi ve teneffüs değerlerini doğrudan kart verilerinden okur ve zaman hesaplamalarını tablo hücrelerinin `dataset` öznitelikleri üzerinden yapar.

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
-   `initContextMenu()`: Ders kartları için sağ tık menüsünü başlatır.
-   `showContextMenu(x, y, lessonCard)`: Özel sağ tık menüsünü oluşturur ve görüntüler.
-   `showScheduleInModal(ownerType, ownerId, title)`: Programı modal içerisinde gösterir.
-   `initModals()`: Ekleme/Güncelleme ve Silme Onayı için Bootstrap modallarını hazırlar.
    -   **Global Enter Yönetimi**: Modal içinde Enter'a basıldığında, focus kapatma butonundaysa focus'u input'a taşır (kapanmayı engeller), diğer durumlarda 'Kaydet' işlemini tetikler.
    -   **Otomatik Focus**: Modal açıldığında (shown olayı) 100ms gecikme ile focus otomatik olarak 'Süre' inputuna zorlanır.
-   `syncTableItems(items)`: Backend'den gelen yeni/güncellenmiş öğeleri tabloya yansıtır.
-   `clearTableItemsByIds(ids)`: Silinen öğeleri tablodan temizler ve hücreleri boşaltır.
-   `handleTableMove()`: Seçili veya sürüklenen öğelerin yeni bir hücreye taşınmasını yönetir (Eskileri siler, yenileri ekler).
-   `handleDeleteDrop()`: Seçili veya sürüklenen öğeleri silme onay modalı ile siler.
-   `saveItem(items)`: AJAX ile verileri kaydeder ve `Toast` ile geri bildirim verir.
