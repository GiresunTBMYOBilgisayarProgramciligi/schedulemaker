[🏠 Ana Sayfa](../../../../../README.md) / [Public](../../../../README.md) / [assets](../../../README.md) / [js](../../README.md) / [admin](../README.md) / **ScheduleCard**

---
# Public\assets\js\admin\ScheduleCard

`ScheduleCard`, projenin frontend tarafındaki en kritik ve karmaşık JavaScript sınıfıdır. Ders programı tablosu üzerindeki tüm sürükle-bırak, çakışma boyama, modal yönetimi ve API senkronizasyon işlemlerini yönetir.

## Sınıf Hiyerarşisi

`ScheduleCard` artık bir temel sınıf (Base Class) olarak hizmet vermektedir. Uygulama içinde doğrudan değil, şu alt sınıflar üzerinden kullanılır:

- [LessonScheduleCard](../LessonScheduleCard.md): Standart haftalık ders programı işlemleri için.
- [ExamScheduleCard](../ExamScheduleCard.md): Sınav haftası programı işlemleri için.

Bu yapı, ders ve sınav programlarının kendine özgü modal, çakışma ve görselleştirme mantıklarını ayrıştırarak kodun bakımını kolaylaştırır.

## Metod Listesi

### Başlatıcılar ve Temel Metotlar
- [constructor](./constructor.md): Sınıfı başlatır ve temel olay dinleyicilerini kurar.
- [initialize](./initialize.md): Sürükle-bırak ve seçim gibi ana modülleri aktif eder.
- [getSchedule](./getSchedule.md): Mevcut ders programı verisini nesne olarak döner.
- [resetDraggedLesson](./resetDraggedLesson.md): Sürükleme verilerini sıfırlar.
- [getDatasetValue](./getDatasetValue.md): Elementlerdeki `data-*` özniteliklerini güvenli şekilde okur.
- [setDraggedLesson](./setDraggedLesson.md): Sürüklenen kartın verilerini merkezi objeye aktarır.

- [getLessonItemData](./getLessonItemData.md): Elementten veri paketi hazırlar.

### Sürükle-Bırak (Drag & Drop)
- [dragStartHandler](./dragStartHandler.md): Sürükleme başladığında veri hazırlığı yapar.
- [dragOverHandler](./dragOverHandler.md): Bırakma alanlarını (cells) belirler.
- [dropHandler](./dropHandler.md): Bırakma anındaki tüm atama veya taşıma mantığını yönetir.

### Seçim ve Düzenleme (Selection & Editing)
- [initBulkSelection](./initBulkSelection.md): Toplu ders seçim mekanizmasını kurar.
- [updateSelectionState](./updateSelectionState.md): Tekil kart seçim görselini günceller.
- [clearSelection](./clearSelection.md): Tüm seçimleri temizler.
- [selectHours](./selectHours.md): Blok ders saati sayısını değiştirir.
- [initContextMenu](./initContextMenu.md): Sağ tık menüsü sistemini başlatır.
- [showContextMenu](./showContextMenu.md): Özel sağ tık menüsünü görselleştirir.
- [showScheduleInModal](./showScheduleInModal.md): Programı modal içerisinde görüntüler.
- [openAssignmentModal](./openAssignmentModal.md): Derslik/Hoca atama penceresini açar.

### Görsel ve Tablo İşlemleri (UI & Table)
- [highlightUnavailableCells](./highlightUnavailableCells.md): Çakışma olan hücreleri vurgular.
- [clearCells](./clearCells.md): Tablodaki görsel vurguları temizler.

- [syncTableItems](./syncTableItems.md): Tablo elemanlarını sunucu verisiyle senkronize eder.
- [clearTableItemsByIds](./clearTableItemsByIds.md): Belirli dersleri tablodan kaldırır.
- [LayoutHelpers](./LayoutHelpers.md): Sticky header ve kaydırma (scroll) yardımcıları (syncWidths, handleScroll vb.).

### Veri ve Zaman Mantığı (Data & Logic)
- [checkCrash](./checkCrash.md): Frontend taraflı hücre çakışma denetimi.
- [checkCrashBackEnd](./checkCrashBackEnd.md): Sunucu taraflı detaylı çakışma denetimi.
- [fetchOptions](./fetchOptions.md): Uygun derslik ve gözetmenleri AJAX ile çeker.
- [generateScheduleItems](./generateScheduleItems.md): DOM elemanlarını kayıt formatına çevirir.
- [saveScheduleItems](./saveScheduleItems.md): Verileri veritabanına kaydeder.
- [deleteScheduleItems](./deleteScheduleItems.md): Kayıtları sistemden siler.
- [TimeHelpers](./TimeHelpers.md): Zaman hesaplama yardımcıları (addMinutes, timeToMinutes vb.).
- **Hata İzleme**: Tüm `Toast` ve `reject` hata mesajlarından önce, hatanın kaynağını ve detaylarını belirten `console.error` logları eklenmiştir. Bu, geliştirme sırasında frontend çakışmalarının nedenini bulmayı kolaylaştırır.

## UX Kuralları
1.  **Sticky Headers**: Uzun tablolarda başlıkların ve ders listesinin ekranın üstüne yapışması.
2.  **Bulk Actions**: 
    - Ders kartına tek tıklama veya checkbox ile seçim.
    - Çift tıklama ile aynı ders adına sahip tüm kartların seçilmesi.
    - Toplu taşıma ve silme desteği.
3.  **Real-time Validation**: Sürükleme anında hücrelerin kırmızı/yeşil boyanması ve `checkCrash` ile anlık kontrol.
4.  **Quick View**: Ders kartına sağ tıklayarak hoca veya derslik programının modalda hızlıca incelenmesi.

*(Not: Her metod için detaylı algoritmik dosyalar bu dizinde mevcuttur.)*
