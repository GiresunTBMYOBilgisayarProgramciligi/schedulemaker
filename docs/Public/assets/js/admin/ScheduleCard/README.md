[🏠 Ana Sayfa](../../../../../README.md) / [Public](../../../../README.md) / [assets](../../../README.md) / [js](../../README.md) / [admin](../README.md) / **ScheduleCard**

---
# Public\assets\js\admin\ScheduleCard

`ScheduleCard`, projenin frontend tarafındaki en kritik ve karmaşık JavaScript sınıfıdır. Ders programı tablosu üzerindeki tüm sürükle-bırak, çakışma boyama, modal yönetimi ve API senkronizasyon işlemlerini yönetir.

## Temel İşleyiş

1.  **Event Orchestration**: Sayfa yüklendiğinde (`initialize`) tüm hücreleri ve kartları dinlemeye başlar.
2.  **Drag & Drop Engine**: Native HTML5 Drag and Drop API'sini kullanarak derslerin taşınmasını sağlar.
3.  **Real-time Validation**: Ders sürüklenirken backend ile asenkron konuşarak (AJAX) uygun olmayan hücreleri gerçek zamanlı olarak işaretler.

## Metod Listesi

### Başlatıcılar ve UI
*   [initialize()](./initialize.md)
*   [initStickyHeaders()](./initStickyHeaders.md)
*   [highlightUnavailableCells()](./highlightUnavailableCells.md)

### Veri ve API
*   [saveScheduleItems()](./saveScheduleItems.md)
*   [deleteScheduleItems()](./deleteScheduleItems.md)
*   [syncTableItems()](./syncTableItems.md)

### Interaction Handlers & UX
*   [dragStartHandler()](./dragStartHandler.md)
*   [dropHandler()](./dropHandler.md): Sürükleme sonrası List-Table-Table branching mantığı.
*   [checkCrash()](./checkCrash.md): Frontend taraflı kural denetimi.

## UX Kuralları
1.  **Sticky Headers**: Uzun tablolarda başlıkların sabit kalması.
2.  **Bulk Actions**: `CTRL` tuşu ile çoklu seçim ve toplu taşıma/silme.
3.  **Real-time Validation**: Sürükleme anında hücrelerin kırmızı/yeşil boyanması.

*(Not: Her metod için detaylı algoritmik dosyalar bu dizinde mevcuttur.)*
